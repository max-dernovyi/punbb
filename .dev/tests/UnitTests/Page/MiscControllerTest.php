<?php
/**
 * misc.php as a module, built with no forum: the rules, marking read with and
 * without the link's token, the OpenSearch description, the form mailing a
 * member and the mail it sends, the form reporting a post and the report it
 * files, and subscribing and unsubscribing.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\ConfirmFormRendering;
use PunBB\Module\Message\Event\ConfirmFormRequested;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Misc\Api\Data\LastVisitInterface;
use PunBB\Module\Misc\Api\Data\MailSentInterface;
use PunBB\Module\Misc\Api\Data\NewReportInterface;
use PunBB\Module\Misc\Api\Data\RecipientInterface;
use PunBB\Module\Misc\Api\Data\ReportedTopicInterface;
use PunBB\Module\Misc\Api\Data\SubscriptionInterface;
use PunBB\Module\Misc\Api\ReadMarksInterface;
use PunBB\Module\Misc\Api\RecipientsInterface;
use PunBB\Module\Misc\Api\ReportingInterface;
use PunBB\Module\Misc\Api\SubscriptionsInterface;
use PunBB\Module\Misc\Controller\MiscController;
use PunBB\Module\Misc\Event\BoardRulesRendering;
use PunBB\Module\Misc\Event\EmailRendering;
use PunBB\Module\Misc\Event\EmailStep;
use PunBB\Module\Misc\Event\MarkingReadStep;
use PunBB\Module\Misc\Event\MiscActionRequested;
use PunBB\Module\Misc\Event\MiscRequested;
use PunBB\Module\Misc\Event\ReportRendering;
use PunBB\Module\Misc\Event\ReportStep;
use PunBB\Module\Misc\Event\SubscriptionStep;
use PunBB\Module\Misc\Model\Recipient;
use PunBB\Module\Misc\Model\ReportedTopic;
use PunBB\Module\Site\Mail\MailerInterface;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Posting\PreparsedMessage;

require_once __DIR__.'/PageFakes.php';

/** Topic 1 "Hello" and forum 1 "News" exist; member 3 is subscribed to topic 2 and forum 2 only. */
final class FakeMiscServices implements ReadMarksInterface, SubscriptionsInterface, RecipientsInterface, ReportingInterface, MailerInterface, PostRulesInterface {
	/** @var list<string> what was written and sent, in order */
	public array $log = array();

	/** @var array<int, Recipient> */
	public array $recipients = array();

	/** @var array<int, ReportedTopic> post id => its topic */
	public array $topics = array();

	public function markBoardRead(LastVisitInterface ...$visits): void {
		foreach ($visits as $visit)
			$this->log[] = 'last visit '.$visit->userId().' at '.$visit->at();
	}

	public function forumName(int $forumId, int $groupId): ?string {
		return array(1 => 'News', 2 => 'Talk')[$forumId] ?? null;
	}

	public function topicSubject(int $topicId, int $groupId): ?string {
		return array(1 => 'Hello', 2 => 'Again')[$topicId] ?? null;
	}

	public function isSubscribedToTopic(int $userId, int $topicId): bool {
		return $topicId === 2;
	}

	public function subscribeToTopic(SubscriptionInterface ...$subscriptions): void {
		foreach ($subscriptions as $subscription)
			$this->log[] = 'subscribe '.$subscription->userId().' to topic '.$subscription->targetId();
	}

	public function subscribedTopicSubject(int $userId, int $topicId): ?string {
		return $topicId === 2 ? 'Again' : null;
	}

	public function unsubscribeFromTopic(SubscriptionInterface ...$subscriptions): void {
		foreach ($subscriptions as $subscription)
			$this->log[] = 'unsubscribe '.$subscription->userId().' from topic '.$subscription->targetId();
	}

	public function isSubscribedToForum(int $userId, int $forumId): bool {
		return $forumId === 2;
	}

	public function subscribeToForum(SubscriptionInterface ...$subscriptions): void {
		foreach ($subscriptions as $subscription)
			$this->log[] = 'subscribe '.$subscription->userId().' to forum '.$subscription->targetId();
	}

	public function unsubscribingForumName(int $forumId, int $groupId): ?string {
		return $this->forumName($forumId, $groupId);
	}

	public function unsubscribeFromForum(SubscriptionInterface ...$subscriptions): void {
		foreach ($subscriptions as $subscription)
			$this->log[] = 'unsubscribe '.$subscription->userId().' from forum '.$subscription->targetId();
	}

	public function find(int $userId): ?RecipientInterface {
		return $this->recipients[$userId] ?? null;
	}

	public function recordMailSent(MailSentInterface ...$sent): void {
		foreach ($sent as $mail)
			$this->log[] = 'mail sent by '.$mail->userId();
	}

	public function topicOf(int $postId): ?ReportedTopicInterface {
		return $this->topics[$postId] ?? null;
	}

	public function add(NewReportInterface ...$reports): void {
		foreach ($reports as $report)
			$this->log[] = 'report '.$report->postId().' in topic '.$report->topicId().' forum '.$report->forumId().' by '.$report->reporterId().': '.$report->message();
	}

	public function send(string $to, string $subject, string $message, bool $quiet = false, string $replyTo = '', string $replyToName = ''): void {
		$this->log[] = 'mail to '.$to.' "'.$subject.'" '.$message.($replyTo !== '' ? ' reply to '.$replyToName.' <'.$replyTo.'>' : '');
	}

	public function subjectMaximumLength(): int {
		return 20;
	}

	public function messageMaximumBytes(): int {
		return 40;
	}

	public function preparse(string $text, array $errors): PreparsedMessage {
		return new PreparsedMessage($text, $errors);
	}

	public function preparseSignature(string $text, array $errors): PreparsedMessage {
		return $this->preparse($text, $errors);
	}
}

class MiscControllerTest extends TestCase {
	private PageKit $kit;

	private FakeMiscServices $services;

	protected function setUp(): void {
		$this->kit = new PageKit(array(MiscRequested::class, MiscActionRequested::class, BoardRulesRendering::class, MarkingReadStep::class, SubscriptionStep::class, EmailStep::class, EmailRendering::class, ReportStep::class, ReportRendering::class,
			MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class, ConfirmFormRequested::class, ConfirmFormRendering::class));
		$this->kit->language->real = array('common', 'misc');
		$this->kit->language->mailTemplates['form_email'] = "Subject: <mail_subject>\n\n<sender> from <board_title> wrote:\n<mail_message>\n--\n<board_mailer>";
		$this->kit->settings->values += array('o_redirect_delay' => '0', 'o_subscriptions' => '1', 'o_rules' => '1', 'o_rules_message' => '<p>Be <b>nice</b></p>', 'o_regs_allow' => '1',
			'o_board_desc' => 'Talk & more', 'o_admin_email' => 'admin@example.com', 'o_cur_version' => '1.5<', 'o_report_method' => '2', 'o_mailing_list' => 'mods@example.com');
		$this->kit->visitor->permissions[] = \PunBB\Module\Site\Visitor\GroupPermission::SendEmail;
		$this->services = new FakeMiscServices();
		$this->services->recipients[5] = new Recipient(5, 'Anna <A>', 'anna@example.com', 0);
		$this->services->topics[9] = new ReportedTopic(4, 'A "topic"', 6);
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $post
	 */
	private function page(array $query = array(), array $post = array()): string {
		$confirmations = new ConfirmPage($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->redirects(), $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->visitor, $this->kit->tokens);
		$controller = new MiscController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(), $confirmations,
			$this->services, $this->services, $this->services, $this->services, $this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter,
			$this->kit->tokens, $this->kit->flash, $this->services, $this->services);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'misc.php', $query, $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	public function testTheRulesAreShownAsTheAdministratorWroteThem(): void {
		$this->kit->events->observe(BoardRulesRendering::class, function (BoardRulesRendering $event): void {
			$event->append('<!-- '.$event->position().' -->');
		});

		$body = $this->page(array('action' => 'rules'));

		$this->assertSame('rules', $this->kit->chromes->opened[0]->id);
		$this->assertSame("200  [rules]<!-- output_start -->\t<div class=\"main-head\">\n\t\t<h2 class=\"hn\"><span>Rules</span></h2>\n\t</div>\n\n\t<div class=\"main-content main-frm\">\n\t\t<div id=\"rules-content\" class=\"ct-box user-box\">\n\t\t\t<p>Be <b>nice</b></p>\n\t\t</div>\n\t</div>\n<!-- end -->", $body);

		$this->kit->settings->values['o_rules'] = '0';
		$this->assertStringContainsString('Bad request', $this->page(array('action' => 'rules')));
	}

	public function testMarkingReadNeedsTheLinksTokenOrAConfirmation(): void {
		$this->assertStringContainsString('name="prev_url"', $this->page(array('action' => 'markread', 'csrf_token' => 'x')));
		$this->assertSame(array(), $this->services->log);

		$steps = array();
		$this->kit->events->observe(MarkingReadStep::class, function (MarkingReadStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.var_export($event->forumId(), true).' '.$event->forumName();
		});

		$this->assertStringStartsWith('302 /index?a=1&b=2 [redirect]', $this->page(array('action' => 'markread', 'csrf_token' => 'token-for-'.md5('markread3'))));
		$this->assertSame(array('last visit 3 at 5000'), $this->services->log);
		$this->assertTrue($this->kit->visitor->forgotTracked);

		$this->assertStringStartsWith('302 /forum/1/slug-news?a=1&b=2 [redirect]', $this->page(array('action' => 'markforumread', 'fid' => '1', 'csrf_token' => 'token-for-'.md5('markforumread13'))));
		$this->assertArrayHasKey(1, $this->kit->visitor->readForums);
		$this->assertStringContainsString('Bad request', $this->page(array('action' => 'markforumread', 'fid' => '7', 'csrf_token' => 'token-for-'.md5('markforumread73'))));
		$this->assertStringContainsString('Bad request', $this->page(array('action' => 'markforumread', 'fid' => array('1'))));

		$this->assertSame(array('selected NULL ', 'marked NULL ', 'selected 1 ', 'marked 1 News', 'selected 7 '), $steps);
		$this->assertSame(array('All topics and forums have been marked as read.', 'All topics in the specified forum have been marked as read.'), $this->kit->flash->info);

		$this->kit->visitor->guest = true;
		$this->assertStringContainsString('You do not have permission', $this->page(array('action' => 'markread')));
	}

	public function testTheOpenSearchDescriptionIsXml(): void {
		$controller = new MiscController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(),
			new ConfirmPage($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->redirects(), $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->visitor, $this->kit->tokens),
			$this->services, $this->services, $this->services, $this->services, $this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter,
			$this->kit->tokens, $this->kit->flash, $this->services, $this->services);
		$this->kit->settings->values['o_show_version'] = '1';

		$response = $controller->handle(new Request('GET', '/', 'misc.php', array('action' => 'opensearch')));

		$this->assertSame('text/xml; charset=utf-8', $response->headers['Content-Type']);
		$this->assertSame('public', $response->headers['Pragma']);
		$this->assertStringStartsWith("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<OpenSearchDescription", $response->body);
		$this->assertStringContainsString("\t<ShortName>Board &amp; Co</ShortName>\n\t<Description>Talk &amp; more</Description>\n", $response->body);
		$this->assertStringContainsString("\t<Image width=\"16\" height=\"16\" type=\"image/x-icon\">http://forum.test/favicon.ico</Image>\n", $response->body);
		$this->assertStringContainsString("\t<Url type=\"application/opensearchdescription+xml\" rel=\"self\" template=\"/opensearch?a=1&amp;b=2\"/>\n\t<Contact>admin@example.com</Contact>\n\t<Attribution>PunBB 1.5&lt;</Attribution>\n", $response->body);
		$this->assertStringEndsWith("\t<moz:SearchForm>/search?a=1&amp;b=2</moz:SearchForm>\n</OpenSearchDescription>\n", $response->body);
	}

	public function testTheMailFormNumbersItsFieldsAndShowsWhatStoppedTheMail(): void {
		$this->kit->events->observe(EmailRendering::class, function (EmailRendering $event): void {
			if ($event->position() === EmailRendering::PRE_SUBJECT)
			{
				$event->append('<input id="fld'.($event->fieldCount() + 1).'" />');
				$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
			}
		});

		$body = $this->page(array('email' => '5'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('formemail', array('Board & Co', 'Send email to Anna &lt;A&gt; via the forum')), array($head->id, array_map(static fn ($crumb): string => $crumb->text, $head->crumbs)));
		$this->assertStringStartsWith("200  [formemail]<div class=\"main-head\">\n\t\t<h2 class=\"hn\"><span>Send email to Anna &lt;A&gt; via the forum</span></h2>", $body);
		$this->assertStringContainsString("<input type=\"hidden\" name=\"form_sent\" value=\"1\" />\n\t\t\t\t<input type=\"hidden\" name=\"redirect_url\" value=\"http://forum.test/before?a=1&amp;b=&quot;2&quot;\" />\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5('/email/5?a=1&amp;b=2')."\" />", $body);
		$this->assertStringContainsString('<input id="fld1" />'."\t\t\t\t<div class=\"sf-set set2\">", $body);
		$this->assertStringContainsString('<input type="text" id="fld2" name="req_subject" value="" size="20" maxlength="20" required />', $body);
		$this->assertStringContainsString('<textarea id="fld3" name="req_message" rows="10" cols="95" required></textarea>', $body);
		$this->assertStringNotContainsString('error-list', $body);

		$this->kit->visitor->lastEmailSentAt = time() - 10;
		$errors = $this->page(array('email' => '5'), array('form_sent' => '1', 'req_subject' => str_repeat('s', 21), 'req_message' => '<hi>'));

		$this->assertStringContainsString("<ul class=\"error-list\">\n\t\t\t\t<li class=\"warn\"><span>Subjects cannot be longer than 20 characters.</span></li>\n\t\t\t\t<li class=\"warn\"><span>At least 60 seconds have to pass between sent emails. Please wait a while and try sending again.</span></li>\n\t\t\t</ul>", $errors);
		$this->assertStringContainsString('required>&lt;hi&gt;</textarea>', $errors);
		$this->assertSame(array(), $this->services->log);
	}

	public function testAMailThatChecksOutIsComposedSentAndRecorded(): void {
		$steps = array();
		$this->kit->events->observe(EmailStep::class, function (EmailStep $event) use (&$steps): void {
			$steps[] = $event->step();

			if ($event->step() === EmailStep::COMPOSED)
				$event->compose('['.$event->mailSubject().']', $event->mailMessage());
		});

		$this->assertStringStartsWith('302 http://forum.test/back?x=1&y=2 [redirect]', $this->page(array('email' => '5'), array('form_sent' => '1', 'req_subject' => ' Hi ', 'req_message' => 'Hello there', 'redirect_url' => 'http://forum.test/back?x=1&y=2')));
		$this->assertSame(array('selected', 'submitted', 'validated', 'composed', 'sent'), $steps);
		$this->assertSame(array('mail to anna@example.com "[Hi]" member from Board & Co wrote:'."\n".'Hello there'."\n--\n".'Board & Co Mailer reply to member <member@example.com>', 'mail sent by 3'), $this->services->log);
		$this->assertSame(array('Email sent.'), $this->kit->flash->info);
	}

	public function testWhoMayBeMailed(): void {
		$this->services->recipients[6] = new Recipient(6, 'Bob', 'bob@example.com', 2);
		$this->services->recipients[7] = new Recipient(7, 'Carl', '', 0);

		$this->assertStringContainsString('has disabled form email', $this->page(array('email' => '6')));
		$this->assertStringContainsString('Bad request', $this->page(array('email' => '7')));
		$this->assertStringContainsString('Bad request', $this->page(array('email' => '1')));
		$this->assertStringContainsString('Bad request', $this->page(array('email' => '8')));

		$this->kit->visitor->moderating = true;
		$this->assertStringContainsString('[formemail]', $this->page(array('email' => '6')));

		$this->kit->visitor->permissions = array();
		$this->assertStringContainsString('You do not have permission', $this->page(array('email' => '5')));
	}

	public function testAReportIsFiledMailedAndRecorded(): void {
		$body = $this->page(array('report' => '9'));
		$this->assertSame('report', $this->kit->chromes->opened[0]->id);
		$this->assertStringContainsString("<input type=\"hidden\" name=\"form_sent\" value=\"1\" />\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5('/report/9?a=1&amp;b=2')."\" />", $body);
		$this->assertStringContainsString('<textarea id="fld1" name="req_reason" rows="5" cols="60" required></textarea>', $body);

		$this->assertStringContainsString('Your report length is 41 bytes. This exceeds the 40 bytes limit.', $this->page(array('report' => '9'), array('form_sent' => '1', 'req_reason' => str_repeat('r', 41))));
		$this->assertStringContainsString('You must enter a reason.', $this->page(array('report' => '9'), array('form_sent' => '1', 'req_reason' => ' ')));

		$this->kit->events->observe(ReportStep::class, function (ReportStep $event): void {
			if ($event->step() === ReportStep::MAILING)
				$event->compose($event->mailSubject().' (probed)', $event->mailMessage());
		});

		$this->assertStringStartsWith('302 /post/9?a=1&b=2 [redirect]', $this->page(array('report' => '9'), array('form_sent' => '1', 'req_reason' => "Spam\r\nhere")));
		$this->assertSame(array(
			"report 9 in topic 4 forum 6 by 3: Spam\nhere",
			'mail to mods@example.com "Report(6) - \'A "topic"\' (probed)" User \'member\' has reported the following message:'."\n/post/9?a=1&amp;b=2\n\nReason:\nSpam\nhere",
			'mail sent by 3',
		), $this->services->log);

		$this->kit->visitor->lastEmailSentAt = time();
		$this->assertStringContainsString('At least 60 seconds have to pass between reports.', $this->page(array('report' => '9'), array('form_sent' => '1', 'req_reason' => 'Spam')));

		$this->kit->visitor->lastEmailSentAt = null;
		$this->assertStringContainsString('Bad request', $this->page(array('report' => '8'), array('form_sent' => '1', 'req_reason' => 'Spam', 'x' => '1')));
	}

	public function testSubscriptionsChangeByTheMembersOwnLink(): void {
		$steps = array();
		$this->kit->events->observe(SubscriptionStep::class, function (SubscriptionStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.$event->target().' '.($event->subscribing() ? '+' : '-').$event->id().' '.$event->name();
		});

		$this->assertStringContainsString('name="prev_url"', $this->page(array('subscribe' => '1', 'csrf_token' => 'token-for-'.md5('subscribe14'))));

		$this->assertStringStartsWith('302 /topic/1/slug-hello?a=1&b=2 [redirect]', $this->page(array('subscribe' => '1', 'csrf_token' => 'token-for-'.md5('subscribe13'))));
		$this->assertStringContainsString('You are already subscribed', $this->page(array('subscribe' => '2', 'csrf_token' => 'token-for-'.md5('subscribe23'))));
		$this->assertStringStartsWith('302 /topic/2/slug-again?a=1&b=2 [redirect]', $this->page(array('unsubscribe' => '2', 'csrf_token' => 'token-for-'.md5('unsubscribe23'))));
		$this->assertStringContainsString('You are not subscribed', $this->page(array('unsubscribe' => '1', 'csrf_token' => 'token-for-'.md5('unsubscribe13'))));
		$this->assertStringStartsWith('302 /forum/1/slug-news?a=1&b=2 [redirect]', $this->page(array('forum_subscribe' => '1', 'csrf_token' => 'token-for-'.md5('forum_subscribe13'))));
		$this->assertStringStartsWith('302 /forum/2/slug-talk?a=1&b=2 [redirect]', $this->page(array('forum_unsubscribe' => '2'), array('csrf_token' => 'posted')));
		$this->assertStringContainsString('You are not subscribed', $this->page(array('forum_unsubscribe' => '9', 'csrf_token' => 'token-for-'.md5('forum_unsubscribe93'))));

		$this->assertSame(array('subscribe 3 to topic 1', 'unsubscribe 3 from topic 2', 'subscribe 3 to forum 1', 'unsubscribe 3 from forum 2'), $this->services->log);
		$this->assertSame(array('selected topic +1 ', 'changed topic +1 Hello', 'selected topic +2 ', 'selected topic -2 ', 'changed topic -2 Again', 'selected topic -1 ',
			'selected forum +1 ', 'changed forum +1 News', 'selected forum -2 ', 'changed forum -2 Talk', 'selected forum -9 '), $steps);

		$this->kit->settings->values['o_subscriptions'] = '0';
		$this->assertStringContainsString('You do not have permission', $this->page(array('subscribe' => '1')));
	}

	public function testAnActionNoOneAnswersIsABadRequest(): void {
		$actions = array();
		$this->kit->events->observe(MiscActionRequested::class, function (MiscActionRequested $event) use (&$actions): void {
			$actions[] = $event->action();
		});

		$this->assertStringContainsString('Bad request', $this->page(array('action' => 'probe')));
		$this->assertStringContainsString('Bad request', $this->page(array('action' => array('rules'))));
		$this->assertSame(array('probe', ''), $actions);
		$this->assertSame(array('MiscRequested', 'MiscActionRequested', 'MessageShowing', 'MessageRendering:start', 'MessageRendering:end', 'MiscRequested', 'MiscActionRequested', 'MessageShowing', 'MessageRendering:start', 'MessageRendering:end'), $this->kit->events->dispatched);
	}
}
