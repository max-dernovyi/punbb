<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Controller;

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
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Post\Api\Data\LocationInterface;
use PunBB\Module\Post\Api\Data\NewPostInterface;
use PunBB\Module\Post\Api\PostingInterface;
use PunBB\Module\Post\Creation\PostCreationInterface;
use PunBB\Module\Post\Event\PostCheckboxesAssembling;
use PunBB\Module\Post\Event\PostingPermissionChecking;
use PunBB\Module\Post\Event\PostingRequested;
use PunBB\Module\Post\Event\PostingStep;
use PunBB\Module\Post\Event\PostPreviewAssembling;
use PunBB\Module\Post\Event\PostRendering;
use PunBB\Module\Post\Event\QuoteSelected;
use PunBB\Module\Post\Event\ReviewPostAssembling;
use PunBB\Module\Post\Model\NewPost;
use PunBB\Module\Post\View\PostView;
use PunBB\Module\Site\Account\UsernameRulesInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Format\TimeFormat;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * post.php?tid= and ?fid=: the form for a reply to a topic or a new topic in a
 * forum, with a preview, the post it quotes and the newest posts of the topic
 * below it; the post stored once the form checks out. The route takes POST out
 * of the gate every other form goes through, so the token is checked here, for
 * every poster.
 */
final class PostController implements ControllerInterface {
	private const TEMPLATE = __DIR__.'/../templates/post.phtml';

	private const REVIEW_TEMPLATE = __DIR__.'/../templates/review.phtml';

	/** The name the guest account posts under, which its form says it is. */
	private const GUEST = 'Guest';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly PostingInterface $posting,
		private readonly PostCreationInterface $creation,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter,
		private readonly CsrfTokensInterface $tokens,
		private readonly PostRulesInterface $rules,
		private readonly UsernameRulesInterface $usernames,
		private readonly EmailAddressesInterface $emails
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new PostingRequested());

		if (!$this->visitor->can(GroupPermission::ReadBoard))
			return $this->messages->respond($this->language->text('common', 'No view'), json: $request->xhr);

		$strings = $this->language->strings('post');

		$topicId = isset($request->query['tid']) ? self::integer($request->query['tid']) : 0;
		$forumId = isset($request->query['fid']) ? self::integer($request->query['fid']) : 0;
		if ($topicId < 1 && $forumId < 1 || $topicId > 0 && $forumId > 0)
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$location = $topicId > 0 ? $this->posting->topic($topicId, $this->visitor->groupId(), $this->visitor->id()) : $this->posting->forum($forumId, $this->visitor->groupId());

		// A redirect forum holds no posts
		if ($location === null || $location->redirectUrl() !== '')
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$checking = new PostingPermissionChecking($location, $this->visitor->isAdministrator() || ($this->visitor->can(GroupPermission::Moderate) && $this->moderates($location)));
		$this->events->dispatch($checking);
		$moderating = $checking->moderating();

		if (!$this->mayPost($location) && !$moderating)
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$this->events->dispatch(new PostingStep(PostingStep::SELECTED, $location, $moderating));

		$posted = null;
		if (isset($request->post['form_sent']))
		{
			$posted = $this->validate($request, $location, $moderating, $strings);
			if ($posted instanceof Response)
				return $posted;

			if ($posted->errors() === array() && !isset($request->post['preview']))
				return $this->store($request, $posted, $strings);
		}

		$quote = null;
		if ($location->topicId() > 0 && isset($request->query['qid']))
		{
			$quote = $this->quote($location, self::integer($request->query['qid']));
			if ($quote === null)
				return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);
		}

		return $this->page($request, $location, $moderating, $posted, $quote, $strings);
	}

	private function moderates(LocationInterface $location): bool {
		foreach ($location->moderators() as $moderator)
			if ($moderator->username() === $this->visitor->username())
				return true;

		return false;
	}

	/** Whether the forum and the visitor's group let them reply to the topic or start one, and the topic is open. */
	private function mayPost(LocationInterface $location): bool {
		if ($location->topicId() > 0)
			return ($location->groupPostsReplies() ?? $this->visitor->can(GroupPermission::PostReplies)) && !$location->topicClosed();

		return $location->groupPostsTopics() ?? $this->visitor->can(GroupPermission::PostTopics);
	}

	/**
	 * The submitted form, checked and tidied, with what stops the post; a
	 * response for a form sent in another visitor's name.
	 *
	 * @param array<string, Html> $strings
	 */
	private function validate(Request $request, LocationInterface $location, bool $moderating, array $strings): PostingStep|Response {
		$submitted = new PostingStep(PostingStep::SUBMITTED, $location, $moderating);
		$this->events->dispatch($submitted);
		$errors = $submitted->errors();

		$formUser = $request->post['form_user'] ?? '';
		if (!is_string($formUser) || $formUser != ($this->visitor->isGuest() ? self::GUEST : $this->visitor->username()))
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$lastPost = $this->visitor->lastPostAt();
		if (!isset($request->post['preview']) && $lastPost !== null && time() - $lastPost < $this->visitor->postFloodInterval() && time() - $lastPost >= 0)
			$errors[] = Html::format(self::string($strings, 'Flood'), $this->visitor->postFloodInterval())->html;

		$subject = null;
		if ($location->topicId() === 0)
		{
			$subject = self::text($request->post['req_subject'] ?? null);

			if ($subject === '')
				$errors[] = self::string($strings, 'No subject')->html;
			else if (mb_strlen($subject) > $this->rules->subjectMaximumLength())
				$errors[] = Html::format(self::string($strings, 'Too long subject'), $this->rules->subjectMaximumLength())->html;
			else if ($this->settings->value('p_subject_all_caps') === '0' && self::allCaps($subject) && !$moderating)
				$errors[] = self::string($strings, 'All caps subject')->html;
		}

		if (!$this->visitor->isGuest())
		{
			$username = $this->visitor->username();
			$email = $this->visitor->email();
		}
		else
		{
			$username = self::text($request->post['req_username'] ?? null);
			$email = strtolower(self::text($request->post[$this->guestEmailField()] ?? null));

			foreach ($this->usernames->validate($username) as $error)
				$errors[] = $error->html;

			if ($this->settings->value('p_force_guest_email') === '1' || $email !== '')
			{
				if (!$this->emails->isValid($email))
					$errors[] = self::string($strings, 'Invalid e-mail')->html;

				if ($this->emails->isBanned($email))
					$errors[] = $this->language->text('profile', 'Banned e-mail')->html;
			}
		}

		if (!$this->tokens->matches($request->post['csrf_token'] ?? null, $this->urls->current()))
			$errors[] = self::string($strings, 'CSRF token mismatch')->html;

		$message = str_replace(array("\r\n", "\r"), "\n", self::text($request->post['req_message'] ?? null));

		if (strlen($message) > $this->rules->messageMaximumBytes())
			$errors[] = Html::format(self::string($strings, 'Too long message'), $this->formatter->number(strlen($message)), $this->formatter->number($this->rules->messageMaximumBytes()))->html;
		else if ($this->settings->value('p_message_all_caps') === '0' && self::allCaps($message) && !$moderating)
			$errors[] = self::string($strings, 'All caps message')->html;

		if ($this->settings->enabled('p_message_bbcode') || $this->settings->enabled('o_make_links'))
		{
			$preparsed = $this->rules->preparse($message, array_map(static fn (string $error): Html => new Html($error), $errors));
			$message = $preparsed->text;
			$errors = array_map(static fn (Html $error): string => $error->html, $preparsed->errors);
		}

		if ($message === '')
			$errors[] = self::string($strings, 'No message')->html;

		$validated = new PostingStep(PostingStep::VALIDATED, $location, $moderating, $errors, $username, $email, $subject, $message, isset($request->post['hide_smilies']), isset($request->post['subscribe']));
		$this->events->dispatch($validated);

		return $validated;
	}

	/** @param array<string, Html> $strings */
	private function store(Request $request, PostingStep $validated, array $strings): Response {
		$location = $validated->location();
		$guest = $this->visitor->isGuest();
		$subscriptions = $this->settings->enabled('o_subscriptions');

		if ($location->topicId() > 0)
			$subscription = $subscriptions && $validated->subscribes() && !$location->subscribed() ? NewPostInterface::SUBSCRIPTION_STARTED
				: ($subscriptions && !$validated->subscribes() && $location->subscribed() ? NewPostInterface::SUBSCRIPTION_ENDED : NewPostInterface::SUBSCRIPTION_KEPT);
		else
			$subscription = $subscriptions && ($request->post['subscribe'] ?? null) == '1' ? NewPostInterface::SUBSCRIPTION_STARTED : NewPostInterface::SUBSCRIPTION_KEPT;

		$proposed = new NewPost(
			$guest,
			$validated->username(),
			$this->visitor->id(),
			$guest && $validated->email() !== '' ? $validated->email() : null,
			$validated->subject() ?? $location->subject(),
			$validated->message(),
			$validated->hidesSmilies(),
			time(),
			$location->topicId(),
			$location->forumId(),
			$location->forumName(),
			$subscription
		);

		$adding = new PostingStep(PostingStep::ADDING, $location, $validated->moderating(), post: $proposed);
		$this->events->dispatch($adding);

		$post = $adding->post() ?? $proposed;
		if ($location->topicId() > 0)
		{
			$postId = $this->creation->reply($post);
			$topicId = 0;
		}
		else
		{
			$created = $this->creation->topic($post);
			$postId = $created->postId;
			$topicId = $created->topicId;
		}

		$this->events->dispatch(new PostingStep(PostingStep::ADDED, $location, $validated->moderating(), post: $post, postId: $postId, topicId: $topicId));

		return $this->redirects->respond($this->urls->link('post', array($postId))->html, self::string($strings, 'Post redirect'), $request->xhr);
	}

	/** The message quoting post $postId of the topic, as the board's markup writes a quote; null for a post the topic does not have. */
	private function quote(LocationInterface $location, int $postId): ?string {
		if ($postId < 1)
			return null;

		$found = $this->posting->quote($postId, $location->topicId());
		if ($found === null)
			return null;

		$selected = new QuoteSelected($location, $postId, $found->poster(), $found->message());
		$this->events->dispatch($selected);

		$poster = $selected->poster();

		if (!$this->settings->enabled('p_message_bbcode'))
			return '> '.$poster.' '.$this->language->text('common', 'wrote')->html.':'."\n\n".'> '.$selected->message()."\n";

		// A name with a square bracket is quoted, so the tag knows where it ends; a quoted name is quoted the other way
		if (str_contains($poster, '[') || str_contains($poster, ']'))
			$poster = str_contains($poster, '\'') ? '"'.$poster.'"' : '\''.$poster.'\'';
		else
		{
			$ends = mb_substr($poster, 0, 1).mb_substr($poster, -1, 1);

			if ($ends === '\'\'')
				$poster = '"'.$poster.'"';
			else if ($ends === '""')
				$poster = '\''.$poster.'\'';
		}

		return '[quote='.$poster.']'.$selected->message().'[/quote]'."\n";
	}

	/**
	 * The form, with the preview or the errors of what was submitted, and the topic review.
	 *
	 * @param ?PostingStep $posted what was submitted, checked; null when nothing was
	 * @param ?string $quote the message a quote starts the form with
	 * @param array<string, Html> $strings
	 */
	private function page(Request $request, LocationInterface $location, bool $moderating, ?PostingStep $posted, ?string $quote, array $strings): Response {
		$common = $this->language->strings('common');
		$reply = $location->topicId() > 0;
		$action = $reply ? $this->urls->link('new_reply', array($location->topicId())) : $this->urls->link('new_topic', array($location->forumId()));

		$hidden = new Parts(array(
			'form_sent'		=> '<input type="hidden" name="form_sent" value="1" />',
			'form_user'		=> Html::format('<input type="hidden" name="form_user" value="%s" />', $this->visitor->isGuest() ? self::GUEST : $this->visitor->username())->html,
			'csrf_token'	=> Html::format('<input type="hidden" name="csrf_token" value="%s" />', $this->tokens->token($action->html))->html,
		));

		$textOptions = new Parts();
		foreach (array('bbcode' => array('p_message_bbcode', 'BBCode'), 'img' => array('p_message_img_tag', 'Images'), 'smilies' => array('o_smilies', 'Smilies')) as $section => [$setting, $label])
			if ($this->settings->enabled($setting))
				$textOptions->set($section, Html::format('<span%s><a class="exthelp" href="%s" title="%s">%s</a></span>', new Html($textOptions->isEmpty() ? ' class="first-item"' : ''),
					$this->urls->link('help', array($section)), Html::format(self::string($common, 'Help page'), self::string($common, $label)), self::string($common, $label))->html);

		$posting = self::string($strings, $reply ? 'Post reply' : 'Post new topic');

		$crumbs = array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb($location->forumName(), $this->urls->link('forum', array($location->forumId(), $this->urls->slug($location->forumName())))),
		);
		if ($reply)
			$crumbs[] = new Crumb($location->subject(), $this->urls->link('topic', array($location->topicId(), $this->urls->slug($location->subject()))));
		$crumbs[] = new Crumb($posting->html);

		$guest = null;
		if ($this->visitor->isGuest())
			$guest = array(
				'username'		=> $posted?->username() ?? '',
				'email'			=> $posted?->email() ?? '',
				'emailName'		=> $this->guestEmailField(),
				'emailRequired'	=> $this->settings->value('p_force_guest_email') === '1',
			);

		$view = new PostView($location, $action->html, array(
			'post'			=> $strings,
			'common'		=> $common,
			'heading'		=> $posting,
			'compose'		=> self::string($strings, $reply ? 'Compose your reply' : 'Compose your topic'),
			'action'		=> $action,
			'guest'			=> $guest,
			'subject'		=> $reply ? null : $posted?->subject() ?? '',
			'subjectLength'	=> $this->rules->subjectMaximumLength(),
			'message'		=> $posted?->message() ?? $quote ?? '',
			'submit'		=> self::string($strings, $reply ? 'Submit reply' : 'Submit topic'),
			'previewLabel'	=> self::string($strings, $reply ? 'Preview reply' : 'Preview topic'),
		));

		return $this->pages->respond(new PageHead('post', $crumbs), fn (): array => array('main' => $this->main($request, $view, $location, $moderating, $posted, $hidden, $textOptions, $strings)));
	}

	/** @param array<string, Html> $strings */
	private function main(Request $request, PostView $view, LocationInterface $location, bool $moderating, ?PostingStep $posted, Parts $hidden, Parts $textOptions, array $strings): Html {
		$reply = $location->topicId() > 0;

		$start = $view->rendering(PostRendering::MAIN_OUTPUT_START, $hidden, new Parts(), $textOptions);
		$this->events->dispatch($start);
		$view->place($start);

		$view->show('hidden', self::joined($start, PostRendering::HIDDEN_FIELDS, "\n\t\t\t\t"));
		$view->show('attributes', $start->names(PostRendering::FORM_ATTRIBUTES) !== array() ? new Html(' '.self::joined($start, PostRendering::FORM_ATTRIBUTES, ' ')->html) : new Html(''));
		$view->show('textOptions', $start->names(PostRendering::TEXT_OPTIONS) !== array() ? Html::format($this->language->text('common', 'You may use'), self::joined($start, PostRendering::TEXT_OPTIONS, ' ')) : null);

		$errors = $posted?->errors() ?? array();

		$preview = null;
		if ($posted !== null && isset($request->post['preview']) && $errors === array())
		{
			$assembling = new PostPreviewAssembling($location, array(
				'num'		=> '<span class="post-num">#</span>',
				'byline'	=> Html::format('<span class="post-byline">%s</span>', Html::format(self::string($strings, $reply ? 'Reply byline' : 'Topic byline'), Html::format('<strong>%s</strong>', $this->visitor->username())))->html,
				'link'		=> Html::format('<span class="post-link">%s</span>', $this->formatter->time(time(), TimeFormat::DateTime))->html,
			), $this->formatter->message((new Html($posted->message()))->trim()->html, $posted->hidesSmilies())->html);
			$this->events->dispatch($assembling);

			$ident = array();
			foreach ($assembling->names() as $name)
				$ident[] = (string) $assembling->entry($name);

			$this->at($view, PostRendering::PREVIEW_NEW_POST_HEAD_OPTION);
			$this->at($view, PostRendering::PREVIEW_NEW_POST_ENTRY_DATA);

			$preview = array(
				'before'	=> new Html($assembling->markup()),
				'heading'	=> self::string($strings, $reply ? 'Preview reply' : 'Preview new topic'),
				'ident'		=> new Html(implode(' ', $ident)),
				'message'	=> new Html($assembling->message()),
			);
		}

		$view->show('preview', $preview);

		$listed = null;
		if ($errors !== array())
		{
			$parts = new Parts();
			foreach ($errors as $number => $error)
				$parts->set((string) $number, '<li class="warn"><span>'.$error.'</span></li>');

			$listed = self::joined($this->at($view, PostRendering::PRE_POST_ERRORS, errors: $parts), PostRendering::ERRORS, "\n\t\t\t\t");
		}

		$view->show('errors', $listed);

		if ($this->visitor->isGuest())
		{
			$this->at($view, PostRendering::PRE_GUEST_INFO_FIELDSET);
			$view->numberGroup('guest_group');

			$this->at($view, PostRendering::PRE_GUEST_USERNAME);
			$view->numberItem('username_item');
			$view->numberField('username_field');

			$this->at($view, PostRendering::PRE_GUEST_EMAIL);
			$view->numberItem('email_item');
			$view->numberField('email_field');

			$this->at($view, PostRendering::PRE_GUEST_INFO_FIELDSET_END);
			$this->at($view, PostRendering::GUEST_INFO_FIELDSET_END);

			$view->restartGroups();
		}

		$this->at($view, PostRendering::PRE_REQ_INFO_FIELDSET);
		$view->numberGroup('group');

		if (!$reply)
		{
			$this->at($view, PostRendering::PRE_REQ_SUBJECT);
			$view->numberItem('subject_item');
			$view->numberField('subject_field');
		}

		$this->at($view, PostRendering::PRE_POST_CONTENTS);
		$view->numberItem('message_item');
		$view->numberField('message_field');

		$checkboxes = array();
		if ($this->settings->enabled('o_smilies'))
			$checkboxes['hide_smilies'] = self::checkbox($view->numberField('hide_smilies'), 'hide_smilies', isset($request->post['hide_smilies']), self::string($strings, 'Hide smilies'));

		if (!$this->visitor->isGuest() && $this->settings->enabled('o_subscriptions'))
		{
			// What the visitor last chose, or what they would keep: every topic they post in, or this one's subscription
			$checked = isset($request->post['preview']) ? isset($request->post['subscribe']) : $this->visitor->subscribesOnReply() || $location->subscribed();

			$checkboxes['subscribe'] = self::checkbox($view->numberField('subscribe'), 'subscribe', $checked, self::string($strings, $location->subscribed() ? 'Stay subscribed' : 'Subscribe'));
		}

		$assembling = $view->checkboxes($checkboxes);
		$this->events->dispatch($assembling);
		$view->placeCheckboxes($assembling);

		$boxes = array();
		foreach ($assembling->names() as $name)
			$boxes[] = (string) $assembling->entry($name);

		$view->show('checkboxesBefore', new Html($assembling->markup()));
		$view->show('checkboxes', $boxes !== array() ? new Html(implode("\n\t\t\t\t\t", $boxes)) : null);

		if ($boxes !== array())
		{
			$view->numberItem('checkboxes_item');
			$this->at($view, PostRendering::PRE_CHECKBOX_FIELDSET_END);
		}

		$this->at($view, PostRendering::PRE_REQ_INFO_FIELDSET_END);
		$this->at($view, PostRendering::REQ_INFO_FIELDSET_END);

		$body = $this->templates->render(self::TEMPLATE, $view->variables());

		$end = $view->rendering(PostRendering::MAIN_OUTPUT_END);
		$this->events->dispatch($end);

		$review = $reply && $this->settings->value('o_topic_review') !== '0' ? $this->review($location, $strings) : '';

		$last = $view->rendering(PostRendering::END);
		$this->events->dispatch($last);

		return (new Html($start->markup().$body.$end->markup().$review.$last->markup()))->trim();
	}

	/**
	 * The newest posts of the topic, newest first, as many as the board reviews.
	 *
	 * @param array<string, Html> $strings
	 */
	private function review(LocationInterface $location, array $strings): string {
		$total = $this->posting->reviewCount($location->topicId());
		$posts = $this->posting->review($location->topicId(), (int) $this->settings->value('o_topic_review'));

		$count = 0;
		$rows = array();
		foreach ($posts as $post)
		{
			++$count;

			$assembling = new ReviewPostAssembling(ReviewPostAssembling::ROW, $location, $post, array(
				'num'		=> Html::format('<span class="post-num">%s</span>', $this->formatter->number($total - $count + 1))->html,
				'byline'	=> Html::format('<span class="post-byline">%s</span>', Html::format(self::string($strings, 'Post byline'), Html::format('<strong>%s</strong>', $post->poster())))->html,
				'link'		=> Html::format('<span class="post-link"><a class="permalink" rel="bookmark" title="%s" href="%s">%s</a></span>', self::string($strings, 'Permalink post'),
					$this->urls->link('post', array($post->id())), $this->formatter->time($post->postedAt(), TimeFormat::DateTime))->html,
			), $this->formatter->message($post->message(), $post->hidesSmilies())->html, $count, count($posts));
			$this->events->dispatch($assembling);
			$count = $assembling->itemCount();

			$ident = array();
			foreach ($assembling->names() as $name)
				$ident[] = (string) $assembling->entry($name);

			$row = array(
				'before'	=> new Html($assembling->markup()),
				'first'		=> $count === 1,
				'last'		=> count($posts) === $count,
				'ident'		=> new Html(implode(' ', $ident)),
				'message'	=> new Html($assembling->message()),
			);

			foreach (array('head' => ReviewPostAssembling::HEAD, 'entry' => ReviewPostAssembling::ENTRY) as $key => $stage)
			{
				$inside = new ReviewPostAssembling($stage, $location, $post, array(), $assembling->message(), $count, count($posts));
				$this->events->dispatch($inside);
				$count = $inside->itemCount();

				$row[$key] = new Html($inside->markup());
			}

			$rows[] = $row;
		}

		return $this->templates->render(self::REVIEW_TEMPLATE, array('heading' => self::string($strings, 'Topic review'), 'posts' => $rows));
	}

	/** @param ?Parts $errors the errors listed, before them */
	private function at(PostView $view, string $position, ?Parts $errors = null): PostRendering {
		$event = $view->rendering($position, errors: $errors);
		$this->events->dispatch($event);
		$view->place($event);

		return $event;
	}

	/** The field a guest's address is posted in: required, or not. */
	private function guestEmailField(): string {
		return $this->settings->value('p_force_guest_email') === '1' ? 'req_email' : 'email';
	}

	private static function checkbox(int $field, string $name, bool $checked, Html $label): string {
		return Html::format('<div class="mf-item"><span class="fld-input"><input type="checkbox" id="fld%s" name="%s" value="1"%s /></span> <label for="fld%s">%s</label></div>',
			$field, $name, new Html($checked ? ' checked="checked"' : ''), $field, $label)->html;
	}

	/** The parts of $group, joined with $glue. */
	private static function joined(PostRendering $event, string $group, string $glue): Html {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[] = (string) $event->entry($group, $name);

		return new Html(implode($glue, $parts));
	}

	/** Whether $text is written in capitals only, with at least one letter that has a lower case; compared as check_is_all_caps() compares, loosely. */
	private static function allCaps(string $text): bool {
		return mb_strtoupper($text) == $text && mb_strtolower($text) != $text;
	}

	/** A value the request carries as text, trimmed; anything else is empty. */
	private static function text(mixed $value): string {
		return is_string($value) ? (new Html($value))->trim()->html : '';
	}

	/** A value the request carries, as intval() took it. */
	private static function integer(mixed $value): int {
		return is_scalar($value) ? intval($value) : (int) ($value !== array() && $value !== null);
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
