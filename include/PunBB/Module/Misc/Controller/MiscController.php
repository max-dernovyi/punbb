<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Misc\Api\ReadMarksInterface;
use PunBB\Module\Misc\Api\RecipientsInterface;
use PunBB\Module\Misc\Api\ReportingInterface;
use PunBB\Module\Misc\Api\SubscriptionsInterface;
use PunBB\Module\Misc\Event\BoardRulesRendering;
use PunBB\Module\Misc\Event\EmailRendering;
use PunBB\Module\Misc\Event\EmailStep;
use PunBB\Module\Misc\Event\MarkingReadStep;
use PunBB\Module\Misc\Event\MiscActionRequested;
use PunBB\Module\Misc\Event\MiscRequested;
use PunBB\Module\Misc\Event\ReportRendering;
use PunBB\Module\Misc\Event\ReportStep;
use PunBB\Module\Misc\Event\SubscriptionStep;
use PunBB\Module\Misc\Model\LastVisit;
use PunBB\Module\Misc\Model\MailSent;
use PunBB\Module\Misc\Model\NewReport;
use PunBB\Module\Misc\Model\Subscription;
use PunBB\Module\Misc\View\FormView;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Mail\MailerInterface;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * misc.php: the board's rules, marking the board or a forum read, the
 * OpenSearch description, mailing a member through the board, reporting a
 * post, and subscribing to topics and forums. A link that changes something
 * carries its token, or the member confirms it.
 */
final class MiscController implements ControllerInterface {
	private const RULES_TEMPLATE = __DIR__.'/../templates/rules.phtml';

	private const OPENSEARCH_TEMPLATE = __DIR__.'/../templates/opensearch.phtml';

	private const EMAIL_TEMPLATE = __DIR__.'/../templates/email.phtml';

	private const REPORT_TEMPLATE = __DIR__.'/../templates/report.phtml';

	/** A member who hides their address and refuses mail through the form. */
	private const REFUSES_FORM_EMAIL = 2;

	/** The guest account and anything below it is no one to mail. */
	private const FIRST_MEMBER = 2;

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly ConfirmPage $confirmations,
		private readonly ReadMarksInterface $readMarks,
		private readonly SubscriptionsInterface $subscriptions,
		private readonly RecipientsInterface $recipients,
		private readonly ReportingInterface $reporting,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash,
		private readonly MailerInterface $mailer,
		private readonly PostRulesInterface $rules
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new MiscRequested());

		$strings = $this->language->strings('misc');
		$action = $request->query['action'] ?? null;

		if ($action === 'rules')
			return $this->boardRules($request);

		if ($action === 'markread')
			return $this->markBoardRead($request, $strings);

		if ($action === 'markforumread')
			return $this->markForumRead($request, $strings);

		if ($action === 'opensearch')
			return $this->openSearch();

		if (isset($request->query['email']))
			return $this->email($request, $strings);

		if (isset($request->query['report']))
			return $this->report($request, $strings);

		foreach (array('subscribe' => array(SubscriptionStep::TOPIC, true), 'unsubscribe' => array(SubscriptionStep::TOPIC, false),
			'forum_subscribe' => array(SubscriptionStep::FORUM, true), 'forum_unsubscribe' => array(SubscriptionStep::FORUM, false)) as $parameter => [$target, $subscribing])
		{
			if (isset($request->query[$parameter]))
				return $this->subscription($request, $parameter, $target, $subscribing, $strings);
		}

		$this->events->dispatch(new MiscActionRequested(is_string($action) ? $action : ''));

		return $this->badRequest($request);
	}

	private function boardRules(Request $request): Response {
		if (!$this->settings->enabled('o_rules') || ($this->visitor->isGuest() && !$this->visitor->can(GroupPermission::ReadBoard) && !$this->settings->enabled('o_regs_allow')))
			return $this->badRequest($request);

		$rules = $this->language->text('common', 'Rules');

		$head = new PageHead('rules', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb($rules->html),
		));

		return $this->pages->respond($head, function () use ($rules): array {
			$start = new BoardRulesRendering(BoardRulesRendering::OUTPUT_START);
			$this->events->dispatch($start);

			$body = $this->templates->render(self::RULES_TEMPLATE, array(
				'heading'	=> $rules,
				'rules'		=> new Html($this->settings->value('o_rules_message')),
			));

			$end = new BoardRulesRendering(BoardRulesRendering::END);
			$this->events->dispatch($end);

			return array('main' => (new Html($start->markup().$body.$end->markup()))->trim());
		});
	}

	/** @param array<string, Html> $strings */
	private function markBoardRead(Request $request, array $strings): Response {
		if ($this->visitor->isGuest())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$confirmation = $this->confirmUntokened($request, 'markread'.$this->visitor->id());
		if ($confirmation !== null)
			return $confirmation;

		$this->events->dispatch(new MarkingReadStep(MarkingReadStep::SELECTED));

		$this->readMarks->markBoardRead(new LastVisit($this->visitor->id(), $this->visitor->loggedAt() ?? time()));
		$this->visitor->forgetTrackedTopics();

		$message = self::string($strings, 'Mark read redirect');
		$this->flash->info($message);

		$this->events->dispatch(new MarkingReadStep(MarkingReadStep::MARKED));

		return $this->redirects->respond($this->urls->link('index')->html, $message, $request->xhr);
	}

	/** @param array<string, Html> $strings */
	private function markForumRead(Request $request, array $strings): Response {
		if ($this->visitor->isGuest())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$forumId = self::id($request->query['fid'] ?? null);
		if ($forumId < 1)
			return $this->badRequest($request);

		$confirmation = $this->confirmUntokened($request, 'markforumread'.$forumId.$this->visitor->id());
		if ($confirmation !== null)
			return $confirmation;

		$this->events->dispatch(new MarkingReadStep(MarkingReadStep::SELECTED, $forumId));

		$name = $this->readMarks->forumName($forumId, $this->visitor->groupId());
		if (!self::found($name))
			return $this->badRequest($request);

		$this->visitor->readForum($forumId, time());

		$message = self::string($strings, 'Mark forum read redirect');
		$this->flash->info($message);

		$this->events->dispatch(new MarkingReadStep(MarkingReadStep::MARKED, $forumId, (string) $name));

		return $this->redirects->respond($this->urls->link('forum', array($forumId, $this->urls->slug((string) $name)))->html, $message, $request->xhr);
	}

	/** The description a browser adds the board's search from. */
	private function openSearch(): Response {
		$body = $this->templates->render(self::OPENSEARCH_TEMPLATE, array(
			'title'			=> $this->settings->value('o_board_title'),
			'description'	=> $this->settings->value('o_board_desc'),
			'base'			=> new Html($this->urls->base()),
			'self'			=> $this->urls->link('opensearch'),
			'contact'		=> $this->settings->value('o_admin_email'),
			'attribution'	=> $this->settings->enabled('o_show_version') ? 'PunBB '.$this->settings->value('o_cur_version') : 'PunBB',
			'search'		=> $this->urls->link('search'),
		));

		return new Response($body, 200, array(
			'Content-Type'	=> 'text/xml; charset=utf-8',
			'Expires'		=> gmdate('D, d M Y H:i:s').' GMT',
			'Cache-Control'	=> 'must-revalidate, post-check=0, pre-check=0',
			'Pragma'		=> 'public',
		));
	}

	/**
	 * The form mailing a member, and the mail once it checks out.
	 *
	 * @param array<string, Html> $strings
	 */
	private function email(Request $request, array $strings): Response {
		if ($this->visitor->isGuest() || !$this->visitor->can(GroupPermission::SendEmail))
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$recipientId = self::id($request->query['email']);
		if ($recipientId < self::FIRST_MEMBER)
			return $this->badRequest($request);

		$this->events->dispatch(new EmailStep(EmailStep::SELECTED, $recipientId));

		$back = is_string($request->post['redirect_url'] ?? null) ? $request->post['redirect_url'] : '';

		if (isset($request->post['cancel']))
			return $this->redirects->respond(Html::escape($back)->html, $this->language->text('common', 'Cancel redirect'), $request->xhr);

		$recipient = $this->recipients->find($recipientId);
		if ($recipient === null)
			return $this->badRequest($request);

		if ($recipient->emailSetting() === self::REFUSES_FORM_EMAIL && !$this->visitor->isModerating())
			return $this->messages->respond(self::string($strings, 'Form e-mail disabled'), json: $request->xhr);

		if ($recipient->email() === '')
			return $this->badRequest($request);

		$errors = array();
		if (isset($request->post['form_sent']))
		{
			$this->events->dispatch(new EmailStep(EmailStep::SUBMITTED, $recipientId, $recipient));

			$subject = self::text($request->post['req_subject'] ?? null);
			$message = self::text($request->post['req_message'] ?? null);

			if ($subject === '')
				$errors[] = self::string($strings, 'No e-mail subject')->html;
			else if (mb_strlen($subject) > $this->rules->subjectMaximumLength())
				$errors[] = sprintf(self::string($strings, 'Too long e-mail subject')->html, $this->rules->subjectMaximumLength());

			if ($message === '')
				$errors[] = self::string($strings, 'No e-mail message')->html;
			else if (strlen($message) > $this->rules->messageMaximumBytes())
				$errors[] = sprintf(self::string($strings, 'Too long e-mail message')->html, $this->formatter->number(strlen($message))->html, $this->formatter->number($this->rules->messageMaximumBytes())->html);

			if ($this->floods($this->visitor->lastEmailSentAt()))
				$errors[] = sprintf(self::string($strings, 'Email flood')->html, $this->visitor->emailFloodInterval());

			$validated = new EmailStep(EmailStep::VALIDATED, $recipientId, $recipient, $subject, $message, $errors);
			$this->events->dispatch($validated);
			$errors = $validated->errors();

			if ($errors === array())
			{
				$template = (new Html($this->language->mailTemplate('form_email')))->trim()->html;

				// The first line is the subject
				$firstLine = (int) strpos($template, "\n");
				$mailSubject = str_replace('<mail_subject>', $subject, (new Html(substr($template, 8, $firstLine - 8)))->trim()->html);
				$mailMessage = (new Html(substr($template, $firstLine)))->trim()->html;
				$mailMessage = str_replace('<sender>', $this->visitor->username(), $mailMessage);
				$mailMessage = str_replace('<board_title>', $this->settings->value('o_board_title'), $mailMessage);
				$mailMessage = str_replace('<mail_message>', $message, $mailMessage);
				$mailMessage = str_replace('<board_mailer>', sprintf($this->language->text('common', 'Forum mailer')->html, $this->settings->value('o_board_title')), $mailMessage);

				$composed = new EmailStep(EmailStep::COMPOSED, $recipientId, $recipient, $subject, $message, array(), $mailSubject, $mailMessage);
				$this->events->dispatch($composed);

				$this->mailer->send($recipient->email(), $composed->mailSubject(), $composed->mailMessage(), replyTo: $this->visitor->email(), replyToName: $this->visitor->username());
				$this->recipients->recordMailSent(new MailSent($this->visitor->id(), time()));

				$sent = self::string($strings, 'E-mail sent redirect');
				$this->flash->info($sent);

				$this->events->dispatch(new EmailStep(EmailStep::SENT, $recipientId, $recipient, $subject, $message));

				return $this->redirects->respond(Html::escape($back)->html, $sent, $request->xhr);
			}
		}

		$heading = Html::format(self::string($strings, 'Send forum e-mail'), $recipient->username());

		$head = new PageHead('formemail', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb($heading->html),
		));

		return $this->pages->respond($head, fn (): array => array('main' => $this->emailForm($request, $recipientId, $heading, $errors, $strings)));
	}

	/**
	 * @param list<string> $errors
	 * @param array<string, Html> $strings
	 */
	private function emailForm(Request $request, int $recipientId, Html $heading, array $errors, array $strings): Html {
		$action = $this->urls->link('email', array($recipientId));

		$view = new FormView(EmailRendering::POSITIONS, array(
			'misc'			=> $strings,
			'common'		=> $this->language->strings('common'),
			'action'		=> $action,
			'heading'		=> $heading,
			'subject'		=> self::submitted($request->post['req_subject'] ?? null),
			'message'		=> self::submitted($request->post['req_message'] ?? null),
			'subjectLength'	=> $this->rules->subjectMaximumLength(),
		));

		$start = new EmailRendering(EmailRendering::OUTPUT_START, $action->html, ...array_merge($view->counts(), array(new Parts(array(
			'form_sent'		=> '<input type="hidden" name="form_sent" value="1" />',
			'redirect_url'	=> Html::format('<input type="hidden" name="redirect_url" value="%s" />', $this->visitor->previousUrl())->html,
			'csrf_token'	=> Html::format('<input type="hidden" name="csrf_token" value="%s" />', $this->tokens->token($action->html))->html,
		)))));
		$this->events->dispatch($start);
		$view->place($start);
		$view->show('hidden', self::joined($start, EmailRendering::HIDDEN_FIELDS));

		$listed = null;
		if ($errors !== array())
		{
			$event = new EmailRendering(EmailRendering::PRE_EMAIL_ERRORS, $action->html, ...array_merge($view->counts(), array(null, self::errorParts($errors))));
			$this->events->dispatch($event);
			$view->place($event);
			$listed = self::joined($event, EmailRendering::ERRORS);
		}

		$view->show('errors', $listed);

		$at = function (string $position) use ($view, $action): void {
			$event = new EmailRendering($position, $action->html, ...$view->counts());
			$this->events->dispatch($event);
			$view->place($event);
		};

		$at(EmailRendering::PRE_FIELDSET);
		$view->numberGroup('group');

		$at(EmailRendering::PRE_SUBJECT);
		$view->numberItem('subject_item');
		$view->numberField('subject_field');

		$at(EmailRendering::PRE_MESSAGE_CONTENTS);
		$view->numberItem('message_item');
		$view->numberField('message_field');

		$at(EmailRendering::PRE_FIELDSET_END);
		$at(EmailRendering::FIELDSET_END);

		$body = $this->templates->render(self::EMAIL_TEMPLATE, $view->variables());

		$end = new EmailRendering(EmailRendering::END, $action->html, ...$view->counts());
		$this->events->dispatch($end);

		return (new Html($start->markup().$body.$end->markup()))->trim();
	}

	/**
	 * The form reporting a post, and the report once it checks out.
	 *
	 * @param array<string, Html> $strings
	 */
	private function report(Request $request, array $strings): Response {
		if ($this->visitor->isGuest())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$postId = self::id($request->query['report']);
		if ($postId < 1)
			return $this->badRequest($request);

		$this->events->dispatch(new ReportStep(ReportStep::SELECTED, $postId));

		if (isset($request->post['cancel']))
			return $this->redirects->respond($this->urls->link('post', array($postId))->html, $this->language->text('common', 'Cancel redirect'), $request->xhr);

		$errors = array();
		if (isset($request->post['form_sent']))
		{
			$this->events->dispatch(new ReportStep(ReportStep::SUBMITTED, $postId));

			if ($this->floods($this->visitor->lastEmailSentAt()))
				return $this->messages->respond(Html::format(self::string($strings, 'Report flood'), $this->visitor->emailFloodInterval()), json: $request->xhr);

			$reason = str_replace(array("\r\n", "\r"), "\n", self::text($request->post['req_reason'] ?? null));
			if ($reason === '')
				return $this->messages->respond(self::string($strings, 'No reason'), json: $request->xhr);

			if (strlen($reason) > $this->rules->messageMaximumBytes())
				$errors[] = sprintf(self::string($strings, 'Too long reason')->html, $this->formatter->number(strlen($reason))->html, $this->formatter->number($this->rules->messageMaximumBytes())->html);

			if ($errors === array())
				return $this->fileReport($request, $postId, $reason, $strings);
		}

		$heading = self::string($strings, 'Report post');

		$head = new PageHead('report', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb($heading->html),
		));

		return $this->pages->respond($head, fn (): array => array('main' => $this->reportForm($postId, $heading, $errors, $strings)));
	}

	/**
	 * Files the report the board's way: into the reports list, to the mailing list, or both.
	 *
	 * @param array<string, Html> $strings
	 */
	private function fileReport(Request $request, int $postId, string $reason, array $strings): Response {
		$topic = $this->reporting->topicOf($postId);
		if ($topic === null)
			return $this->badRequest($request);

		$this->events->dispatch(new ReportStep(ReportStep::REPORTING, $postId, $reason, $topic));

		$method = (int) $this->settings->value('o_report_method');
		$link = $this->urls->link('post', array($postId))->html;

		if ($method === 0 || $method === 2)
			$this->reporting->add(new NewReport($postId, $topic->id(), $topic->forumId(), $this->visitor->id(), time(), $reason));

		if (($method === 1 || $method === 2) && $this->settings->value('o_mailing_list') !== '')
		{
			$mailing = new ReportStep(ReportStep::MAILING, $postId, $reason, $topic,
				'Report('.$topic->forumId().') - \''.$topic->subject().'\'',
				'User \''.$this->visitor->username().'\' has reported the following message:'."\n".$link."\n\n".'Reason:'."\n".$reason);
			$this->events->dispatch($mailing);

			$this->mailer->send($this->settings->value('o_mailing_list'), $mailing->mailSubject(), $mailing->mailMessage());
		}

		$this->reporting->recordMailSent(new MailSent($this->visitor->id(), time()));

		$message = self::string($strings, 'Report redirect');
		$this->flash->info($message);

		$this->events->dispatch(new ReportStep(ReportStep::REPORTED, $postId, $reason, $topic));

		return $this->redirects->respond($link, $message, $request->xhr);
	}

	/**
	 * @param list<string> $errors
	 * @param array<string, Html> $strings
	 */
	private function reportForm(int $postId, Html $heading, array $errors, array $strings): Html {
		$action = $this->urls->link('report', array($postId));

		$view = new FormView(ReportRendering::POSITIONS, array(
			'misc'		=> $strings,
			'common'	=> $this->language->strings('common'),
			'action'	=> $action,
			'heading'	=> $heading,
		));

		$start = new ReportRendering(ReportRendering::OUTPUT_START, $action->html, ...array_merge($view->counts(), array(new Parts(array(
			'form_sent'		=> '<input type="hidden" name="form_sent" value="1" />',
			'csrf_token'	=> Html::format('<input type="hidden" name="csrf_token" value="%s" />', $this->tokens->token($action->html))->html,
		)))));
		$this->events->dispatch($start);
		$view->place($start);
		$view->show('hidden', self::joined($start, ReportRendering::HIDDEN_FIELDS));

		$listed = null;
		if ($errors !== array())
		{
			$event = new ReportRendering(ReportRendering::PRE_REPORT_ERRORS, $action->html, ...array_merge($view->counts(), array(null, self::errorParts($errors))));
			$this->events->dispatch($event);
			$view->place($event);
			$listed = self::joined($event, ReportRendering::ERRORS);
		}

		$view->show('errors', $listed);

		$at = function (string $position) use ($view, $action): void {
			$event = new ReportRendering($position, $action->html, ...$view->counts());
			$this->events->dispatch($event);
			$view->place($event);
		};

		$at(ReportRendering::PRE_FIELDSET);
		$view->numberGroup('group');

		$at(ReportRendering::PRE_REASON);
		$view->numberItem('reason_item');
		$view->numberField('reason_field');

		$at(ReportRendering::PRE_FIELDSET_END);
		$at(ReportRendering::FIELDSET_END);

		$body = $this->templates->render(self::REPORT_TEMPLATE, $view->variables());

		$end = new ReportRendering(ReportRendering::END, $action->html, ...$view->counts());
		$this->events->dispatch($end);

		return (new Html($start->markup().$body.$end->markup()))->trim();
	}

	/**
	 * Subscribing to a topic or a forum, or unsubscribing, by the member's own link.
	 *
	 * @param array<string, Html> $strings
	 */
	private function subscription(Request $request, string $parameter, string $target, bool $subscribing, array $strings): Response {
		if ($this->visitor->isGuest() || !$this->settings->enabled('o_subscriptions'))
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$id = self::id($request->query[$parameter]);
		if ($id < 1)
			return $this->badRequest($request);

		$userId = $this->visitor->id();

		$confirmation = $this->confirmUntokened($request, $parameter.$id.$userId);
		if ($confirmation !== null)
			return $confirmation;

		$this->events->dispatch(new SubscriptionStep(SubscriptionStep::SELECTED, $target, $subscribing, $id));

		$subscription = new Subscription($userId, $id);
		$topic = $target === SubscriptionStep::TOPIC;

		if ($subscribing)
		{
			$name = $topic ? $this->subscriptions->topicSubject($id, $this->visitor->groupId()) : $this->subscriptions->forumName($id, $this->visitor->groupId());
			if (!self::found($name))
				return $this->badRequest($request);

			if ($topic ? $this->subscriptions->isSubscribedToTopic($userId, $id) : $this->subscriptions->isSubscribedToForum($userId, $id))
				return $this->messages->respond(self::string($strings, 'Already subscribed'), json: $request->xhr);

			if ($topic)
				$this->subscriptions->subscribeToTopic($subscription);
			else
				$this->subscriptions->subscribeToForum($subscription);

			$message = self::string($strings, 'Subscribe redirect');
		}
		else
		{
			$name = $topic ? $this->subscriptions->subscribedTopicSubject($userId, $id) : $this->subscriptions->unsubscribingForumName($id, $this->visitor->groupId());
			if (!self::found($name))
				return $this->messages->respond(self::string($strings, 'Not subscribed'), json: $request->xhr);

			if ($topic)
				$this->subscriptions->unsubscribeFromTopic($subscription);
			else
				$this->subscriptions->unsubscribeFromForum($subscription);

			$message = self::string($strings, 'Unsubscribe redirect');
		}

		$this->flash->info($message);

		$this->events->dispatch(new SubscriptionStep(SubscriptionStep::CHANGED, $target, $subscribing, $id, (string) $name));

		return $this->redirects->respond($this->urls->link($topic ? 'topic' : 'forum', array($id, $this->urls->slug((string) $name)))->html, $message, $request->xhr);
	}

	/** The confirmation a link without its token asks for; null when the token checks out or nothing is to be confirmed. */
	private function confirmUntokened(Request $request, string $target): ?Response {
		// A token posted has passed the gate every POST goes through; one in the link is checked here
		if (isset($request->post['csrf_token']) || $this->tokens->matches($request->query['csrf_token'] ?? null, $target))
			return null;

		return $this->confirmations->respond($request->post, $request->xhr);
	}

	/** Whether the member mailed or reported less than their group's interval ago. */
	private function floods(?int $lastSent): bool {
		if ($lastSent === null)
			return false;

		$since = time() - $lastSent;

		return $since < $this->visitor->emailFloodInterval() && $since >= 0;
	}

	private function badRequest(Request $request): Response {
		return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);
	}

	/**
	 * @param list<string> $errors
	 */
	private static function errorParts(array $errors): Parts {
		$parts = new Parts();
		foreach ($errors as $number => $error)
			$parts->set((string) $number, '<li class="warn"><span>'.$error.'</span></li>');

		return $parts;
	}

	/** The parts of $group, one per line. */
	private static function joined(EmailRendering|ReportRendering $event, string $group): Html {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[] = (string) $event->entry($group, $name);

		return new Html(implode("\n\t\t\t\t", $parts));
	}

	/** A topic's subject or a forum's name as a page script tested it: nothing, '' and '0' found nothing. */
	private static function found(?string $name): bool {
		return $name !== null && $name !== '' && $name !== '0';
	}

	/** An id the request carries as a single value; 0 for anything else. */
	private static function id(mixed $value): int {
		return is_scalar($value) ? intval($value) : 0;
	}

	/** A value the request carries as text, trimmed; anything else is empty. */
	private static function text(mixed $value): string {
		return is_string($value) ? (new Html($value))->trim()->html : '';
	}

	/** What the visitor typed into a field, shown back as they typed it; nothing for a value that is no text. */
	private static function submitted(mixed $value): string {
		return is_string($value) ? $value : '';
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
