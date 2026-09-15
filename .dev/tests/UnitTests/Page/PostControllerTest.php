<?php
/**
 * post.php as a module, built with no forum: who may post where, the form for
 * a reply, a new topic and a guest, the numbers of its fields and its
 * checkboxes as observers add some, the quote, the preview, the errors of
 * what was submitted, the topic review, and what a valid post stores and
 * where it sends the visitor.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Post\Api\Data\LocationInterface;
use PunBB\Module\Post\Api\Data\NewPostInterface;
use PunBB\Module\Post\Api\Data\QuoteInterface;
use PunBB\Module\Post\Api\PostingInterface;
use PunBB\Module\Post\Controller\PostController;
use PunBB\Module\Post\Creation\CreatedTopic;
use PunBB\Module\Post\Creation\PostCreationInterface;
use PunBB\Module\Post\Event\PostCheckboxesAssembling;
use PunBB\Module\Post\Event\PostingPermissionChecking;
use PunBB\Module\Post\Event\PostingRequested;
use PunBB\Module\Post\Event\PostingStep;
use PunBB\Module\Post\Event\PostPreviewAssembling;
use PunBB\Module\Post\Event\PostRendering;
use PunBB\Module\Post\Event\QuoteSelected;
use PunBB\Module\Post\Event\ReviewPostAssembling;
use PunBB\Module\Post\Model\Location;
use PunBB\Module\Post\Model\Moderator;
use PunBB\Module\Post\Model\NewPost;
use PunBB\Module\Post\Model\Quote;
use PunBB\Module\Post\Model\ReviewPost;
use PunBB\Module\Site\Account\UsernameRulesInterface;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Posting\PreparsedMessage;
use PunBB\Module\Site\Visitor\GroupPermission;

require_once __DIR__.'/PageFakes.php';

final class FakePosting implements PostingInterface, PostRulesInterface, UsernameRulesInterface, EmailAddressesInterface {
	/** @var array<int, LocationInterface> */
	public array $topics = array();

	/** @var array<int, LocationInterface> */
	public array $forums = array();

	/** @var array<int, QuoteInterface> */
	public array $quotes = array();

	/** @var list<ReviewPost> */
	public array $review = array();

	/** @var list<NewPostInterface> */
	public array $stored = array();

	/** @var list<string> */
	public array $log = array();

	public function topic(int $topicId, int $groupId, int $userId): ?LocationInterface {
		return $this->topics[$topicId] ?? null;
	}

	public function forum(int $forumId, int $groupId): ?LocationInterface {
		return $this->forums[$forumId] ?? null;
	}

	public function quote(int $postId, int $topicId): ?QuoteInterface {
		return $this->quotes[$postId] ?? null;
	}

	public function reviewCount(int $topicId): int {
		return 12;
	}

	public function review(int $topicId, int $limit): array {
		$this->log[] = 'review '.$topicId.' of '.$limit;

		return $this->review;
	}

	public function subjectMaximumLength(): int {
		return 20;
	}

	public function messageMaximumBytes(): int {
		return 40;
	}

	public function preparse(string $text, array $errors): PreparsedMessage {
		if ($errors === array() && str_contains($text, '[bad]'))
			$errors[] = new Html('Bad <b>tag</b>');

		return new PreparsedMessage(str_replace('[B]', '[b]', $text), $errors);
	}

	public function preparseSignature(string $text, array $errors): PreparsedMessage {
		return $this->preparse($text, $errors);
	}

	public function validate(string $username, ?int $exceptUserId = null): array {
		return strlen($username) < 2 ? array(new Html('Username <b>too short</b>')) : array();
	}

	public function isValid(string $address): bool {
		return str_contains($address, '@');
	}

	public function isBanned(string $address): bool {
		return str_ends_with($address, '@banned.invalid');
	}
}

/** Stores into the fake's list: a reply as post 31, a topic as topic 8 opened by post 32. */
final class FakePostCreation implements PostCreationInterface {
	public function __construct(private FakePosting $posting) {}

	public function reply(NewPostInterface $post): int {
		$this->posting->stored[] = $post;

		return 31;
	}

	public function topic(NewPostInterface $post): CreatedTopic {
		$this->posting->stored[] = $post;

		return new CreatedTopic(8, 32);
	}
}

class PostControllerTest extends TestCase {
	private PageKit $kit;

	private FakePosting $posting;

	protected function setUp(): void {
		$this->kit = new PageKit(array(PostingRequested::class, PostingPermissionChecking::class, PostingStep::class, QuoteSelected::class, PostRendering::class, PostPreviewAssembling::class,
			PostCheckboxesAssembling::class, ReviewPostAssembling::class, MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class));
		$this->kit->language->real = array('post', 'common', 'profile');
		$this->kit->settings->values += array('o_redirect_delay' => '0', 'o_smilies' => '1', 'p_message_bbcode' => '1', 'p_message_img_tag' => '0', 'p_subject_all_caps' => '0', 'p_message_all_caps' => '0',
			'o_subscriptions' => '1', 'o_topic_review' => '5', 'p_force_guest_email' => '0');
		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard, GroupPermission::PostReplies, GroupPermission::PostTopics);

		$this->posting = new FakePosting();
		$this->posting->topics = array(
			2 => new Location(1, 'Forum & 1', array(new Moderator(7, 'mod')), '', null, null, 2, 'Topic <2>', false, true),
			4 => new Location(1, 'Forum & 1', array(new Moderator(3, 'member')), '', null, null, 4, 'Closed', true, false),
			6 => new Location(5, 'No replies', array(), '', false, null, 6, 'Locked out', false, false),
		);
		$this->posting->forums = array(
			1 => new Location(1, 'Forum & 1', array(), '', null, null),
			3 => new Location(3, 'Away', array(), 'http://example.com/', null, null),
		);
		$this->posting->quotes = array(9 => new Quote('[anna]', 'Quoted <text>'), 10 => new Quote('"bob"', 'Hi'));
		$this->posting->review = array(new ReviewPost(12, 'anna', 'Newest', false, 500), new ReviewPost(11, 'bob <b>', 'Older', true, 400));
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $post
	 */
	private function page(array $query, array $post = array()): string {
		$controller = new PostController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(), $this->posting, new FakePostCreation($this->posting),
			$this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter, $this->kit->tokens, $this->posting, $this->posting, $this->posting);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'post.php', $query, $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	/**
	 * A form as the member sends it, with the token for $action.
	 *
	 * @param array<string, string> $fields
	 * @return array<string, string>
	 */
	private function sent(array $fields): array {
		return $fields + array('form_sent' => '1', 'form_user' => $this->kit->visitor->guest ? 'Guest' : 'member', 'csrf_token' => $this->kit->tokens->token($this->kit->urls->current()));
	}

	public function testWhereAVisitorMayPost(): void {
		$bad = '<p>Bad request.';

		$this->assertStringContainsString($bad, $this->page(array()));
		$this->assertStringContainsString($bad, $this->page(array('tid' => '2', 'fid' => '1')));
		$this->assertStringContainsString($bad, $this->page(array('tid' => '99')));
		$this->assertStringContainsString($bad, $this->page(array('fid' => '3')), 'a redirect forum');
		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('tid' => '4')), 'a closed topic');
		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('tid' => '6')), 'a forum its group may not reply in');

		$this->kit->visitor->permissions[] = GroupPermission::Moderate;
		$this->assertStringContainsString('200  [post]', $this->page(array('tid' => '4')), 'a moderator of the forum');

		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard, GroupPermission::PostTopics);
		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('tid' => '2')), 'a group that may not reply');
		$this->assertStringContainsString('200  [post]', $this->page(array('fid' => '1')));
		$this->assertSame(array(), $this->posting->stored);
	}

	public function testAReplysFormHasItsCheckboxesAndTheTopicReview(): void {
		$body = $this->page(array('tid' => '2'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame('post', $head->id);
		$this->assertSame(array('Board & Co', 'Forum & 1', 'Topic <2>', 'Post new reply'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));

		$action = '/new_reply/2?a=1&amp;b=2';
		$this->assertStringStartsWith("200  [post]<div class=\"main-head\">\n\t\t<h2 class=\"hn\"><span>Post new reply</span></h2>\n\t</div>\n\t<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Compose and post your new reply</span></h2>", $body);
		$this->assertStringContainsString("action=\"$action\">\n\t\t\t<div class=\"hidden\">\n\t\t\t\t<input type=\"hidden\" name=\"form_sent\" value=\"1\" />\n\t\t\t\t<input type=\"hidden\" name=\"form_user\" value=\"member\" />\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5($action).'" />', $body);
		$this->assertStringContainsString("\t\t\t<fieldset class=\"frm-group group1\">\n\t\t\t\t<legend class=\"group-legend\"><strong>Required information</strong></legend>\n\t\t\t\t<div class=\"txt-set set1\">", $body);
		$this->assertStringContainsString('<textarea id="fld1" name="req_message" rows="15" cols="95" required spellcheck="true"></textarea>', $body);
		$this->assertStringContainsString("<fieldset class=\"mf-set set2\">\n\t\t\t\t\t<div class=\"mf-box checkbox\">\n\t\t\t\t\t\t<div class=\"mf-item\"><span class=\"fld-input\"><input type=\"checkbox\" id=\"fld2\" name=\"hide_smilies\" value=\"1\" /></span> <label for=\"fld2\">Never show smilies as icons for this post.</label></div>\n\t\t\t\t\t<div class=\"mf-item\"><span class=\"fld-input\"><input type=\"checkbox\" id=\"fld3\" name=\"subscribe\" value=\"1\" checked=\"checked\" /></span> <label for=\"fld3\">Stay subscribed to this topic.</label></div>\n\t\t\t\t\t</div>\n\t\t\t\t</fieldset>\n\t\t\t</fieldset>", $body);
		$this->assertStringNotContainsString('req_subject', $body);
		$this->assertStringContainsString("\t\t</form>\n\t</div>\n\t<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Topic review (newest first)</span></h2>\n\t</div>\n\t<div id=\"topic-review\" class=\"main-content main-frm\">\n\t\t<div class=\"post firstpost\">", $body);
		$this->assertStringContainsString('<h3 class="hn post-ident"><span class="post-num">12</span> <span class="post-byline"><span>Post by </span><strong>anna</strong></span> <span class="post-link"><a class="permalink" rel="bookmark" title="Permanent link to this post" href="/post/12?a=1&amp;b=2"><time>500</time></a></span></h3>', $body);
		$this->assertStringContainsString("<div class=\"post lastpost\">\n\t\t\t<div class=\"posthead\">\n\t\t\t\t<h3 class=\"hn post-ident\"><span class=\"post-num\">11</span> <span class=\"post-byline\"><span>Post by </span><strong>bob &lt;b&gt;</strong>", $body);
		$this->assertStringEndsWith("<div class=\"entry-content\">\n\t\t\t\t\t\t<p>Older (no smilies)</p>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t</div>\n\t</div>", $body);
		$this->assertSame(array('review 2 of 5'), $this->posting->log);
		$this->assertSame(array('PostingRequested', 'PostingPermissionChecking', 'PostingStep', 'PostRendering:main_output_start', 'PostRendering:pre_req_info_fieldset', 'PostRendering:pre_post_contents',
			'PostCheckboxesAssembling', 'PostRendering:pre_checkbox_fieldset_end', 'PostRendering:pre_req_info_fieldset_end', 'PostRendering:req_info_fieldset_end', 'PostRendering:main_output_end',
			'ReviewPostAssembling', 'ReviewPostAssembling', 'ReviewPostAssembling', 'ReviewPostAssembling', 'ReviewPostAssembling', 'ReviewPostAssembling', 'PostRendering:end'), $this->kit->events->dispatched);
	}

	public function testANewTopicsFormHasItsSubjectAndNoReview(): void {
		$this->kit->settings->values['o_smilies'] = '0';

		$body = $this->page(array('fid' => '1'));

		$this->assertSame(array('Board & Co', 'Forum & 1', 'Post new topic'), array_map(static fn ($crumb): string => $crumb->text, $this->kit->chromes->opened[0]->crumbs));
		$this->assertStringContainsString("<legend class=\"group-legend\"><strong>Required information</strong></legend>\n\t\t\t\t<div class=\"sf-set set1\">\n\t\t\t\t\t<div class=\"sf-box text required longtext\">", $body);
		$this->assertStringContainsString('<input id="fld1" type="text" name="req_subject" value="" size="20" maxlength="20" required /></span>', $body);
		$this->assertStringContainsString('<textarea id="fld2"', $body);
		$this->assertStringContainsString('<fieldset class="mf-set set3">', $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld3" name="subscribe" value="1" /></span> <label for="fld3">Subscribe to this topic.</label>', $body);
		$this->assertStringContainsString('<input type="submit" name="preview" value="Preview topic" />', $body);
		$this->assertStringNotContainsString('topic-review', $body);
	}

	public function testAGuestsFormAsksForTheirNameAndAddressAndNumbersItsGroupsAfresh(): void {
		$this->kit->visitor->guest = true;
		$this->kit->settings->values['p_force_guest_email'] = '1';

		$body = $this->page(array('tid' => '2'));

		$this->assertStringContainsString('<input type="hidden" name="form_user" value="Guest" />', $body);
		$this->assertStringContainsString("\t\t\t</div>\n\t\t\t<fieldset class=\"frm-group group1\">\n\t\t\t\t<legend class=\"group-legend\"><strong>Required information for guests</strong></legend>\n\t\t\t\t<div class=\"sf-set set1\">", $body);
		$this->assertStringContainsString('<input type="text" id="fld1" name="req_username" value="" size="35" maxlength="25" /></span>', $body);
		$this->assertStringContainsString("<div class=\"sf-set set2\">\n\t\t\t\t\t<div class=\"sf-box text required\">", $body);
		$this->assertStringContainsString('<input type="email" id="fld2" name="req_email" value="" size="35" maxlength="80" required /></span>', $body);
		$this->assertStringContainsString("\t\t\t</fieldset>\n\t\t\t<fieldset class=\"frm-group group1\">\n\t\t\t\t<legend class=\"group-legend\"><strong>Required information</strong></legend>\n\t\t\t\t<div class=\"txt-set set1\">", $body);
		$this->assertStringContainsString('<textarea id="fld3"', $body);
		$this->assertStringNotContainsString('name="subscribe"', $body);

		$this->kit->settings->values['p_force_guest_email'] = '0';
		$this->assertStringContainsString("<div class=\"sf-box text\">\n\t\t\t\t\t\t<label for=\"fld2\"><span>Guest email</span></label><br />\n\t\t\t\t\t\t<span class=\"fld-input\"><input type=\"email\" id=\"fld2\" name=\"email\" value=\"\" size=\"35\" maxlength=\"80\"  /></span>", $this->page(array('tid' => '2')));
	}

	public function testAQuoteStartsTheMessageMarkedUpForTheBoard(): void {
		$this->assertStringContainsString('spellcheck="true">[quote=&#039;[anna]&#039;]Quoted &lt;text&gt;[/quote]'."\n".'</textarea>', $this->page(array('tid' => '2', 'qid' => '9')));
		$this->assertStringContainsString('spellcheck="true">[quote=&#039;&quot;bob&quot;&#039;]Hi[/quote]'."\n".'</textarea>', $this->page(array('tid' => '2', 'qid' => '10')));
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('tid' => '2', 'qid' => '11')));
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('tid' => '2', 'qid' => '0')));

		$this->kit->events->observe(QuoteSelected::class, static function (QuoteSelected $event): void {
			$event->change($event->poster(), 'hidden');
		});
		$this->kit->settings->values['p_message_bbcode'] = '0';

		$this->assertStringContainsString('spellcheck="true">&gt; &quot;bob&quot; wrote:'."\n\n".'&gt; hidden'."\n".'</textarea>', $this->page(array('tid' => '2', 'qid' => '10')));
	}

	public function testObserversChangeTheFormsPartsTheNumbersAndTheCheckboxes(): void {
		$this->kit->events->observe(PostRendering::class, function (PostRendering $event): void {
			if ($event->position() === PostRendering::MAIN_OUTPUT_START)
			{
				$event->set(PostRendering::HIDDEN_FIELDS, 'probe', '<input type="hidden" name="probe" />');
				$event->set(PostRendering::FORM_ATTRIBUTES, 'probe', 'data-probe="1"');
				$event->remove(PostRendering::TEXT_OPTIONS, 'smilies');
			}

			if ($event->position() === PostRendering::PRE_POST_CONTENTS)
			{
				$event->append('<!-- probe -->');
				$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
			}
		});
		$this->kit->events->observe(PostCheckboxesAssembling::class, function (PostCheckboxesAssembling $event): void {
			$event->remove('subscribe');
			$event->set('probe', '<div class="mf-item"><input id="fld'.($event->fieldCount() + 1).'" /></div>');
			$event->count($event->groupCount(), $event->itemCount(), $event->fieldCount() + 1);
		});
		$this->kit->events->observe(ReviewPostAssembling::class, function (ReviewPostAssembling $event): void {
			if ($event->stage() === ReviewPostAssembling::ENTRY)
				$event->append('<p>entry of '.$event->post()->id().'</p>'."\n");
		});

		$body = $this->page(array('tid' => '2'));

		$this->assertStringContainsString('action="/new_reply/2?a=1&amp;b=2" data-probe="1">', $body);
		$this->assertStringContainsString("\n\t\t\t\t<input type=\"hidden\" name=\"probe\" />\n\t\t\t</div>", $body);
		$this->assertStringNotContainsString('/help/smilies', $body);
		$this->assertStringContainsString("<!-- probe -->\t\t\t\t<div class=\"txt-set set2\">", $body);
		$this->assertStringContainsString('<textarea id="fld2"', $body);
		$this->assertStringContainsString("<div class=\"mf-item\"><input id=\"fld5\" /></div>\n\t\t\t\t\t</div>", $body, 'the subscription removed had taken its field\'s number');
		$this->assertStringNotContainsString('name="subscribe"', $body);
		$this->assertStringContainsString("<p>Newest</p>\n<p>entry of 12</p>\n\t\t\t\t\t</div>", $body);
	}

	public function testAPreviewShowsTheCheckedMessageAboveTheForm(): void {
		$body = $this->page(array('tid' => '2'), $this->sent(array('preview' => '1', 'req_message' => "  A [B]reply[/B]\r\nhere  ", 'hide_smilies' => '1')));

		$this->assertStringContainsString("<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Preview reply</span></h2>", $body);
		$this->assertStringContainsString('<h3 class="hn"><span class="post-num">#</span> <span class="post-byline"><span>Reply by </span><strong>member</strong></span> <span class="post-link"><time>', $body);
		$this->assertStringContainsString("<div class=\"entry-content\">\n\t\t\t\t\t\t<p>A [b]reply[/B]\nhere (no smilies)</p>\n\t\t\t\t\t</div>", $body);
		$this->assertStringContainsString("spellcheck=\"true\">A [b]reply[/B]\nhere</textarea>", $body);
		$this->assertStringContainsString('name="hide_smilies" value="1" checked="checked" />', $body);
		$this->assertStringContainsString('name="subscribe" value="1" /></span> <label for="fld3">Stay subscribed', $body, 'a preview keeps what the visitor chose');
		$this->assertSame(array(), $this->posting->stored);
	}

	public function testWhatStopsAPostIsListedAndStoresNothing(): void {
		$this->kit->visitor->lastPostAt = time() - 5;
		$this->kit->events->observe(PostingStep::class, function (PostingStep $event): void {
			if ($event->step() === PostingStep::VALIDATED)
				$event->setErrors(array_merge($event->errors(), array('Probe <em>error</em>')));
		});

		$body = $this->page(array('fid' => '1'), $this->sent(array('req_subject' => 'SHOUTED', 'req_message' => str_repeat('m', 41))));

		$this->assertStringContainsString("<h2 class=\"warn hn\"><strong>Warning!</strong> The following errors must be corrected before your message can be posted:</h2>\n\t\t\t<ul class=\"error-list\">\n\t\t\t\t<li class=\"warn\"><span>At least 30 seconds have to pass between posts. Please wait a while and try posting again.</span></li>\n\t\t\t\t<li class=\"warn\"><span>Subjects cannot contain only capital letters.</span></li>\n\t\t\t\t<li class=\"warn\"><span>Your post length is 41 bytes. This exceeds the 40 bytes limit.</span></li>\n\t\t\t\t<li class=\"warn\"><span>Probe <em>error</em></span></li>\n\t\t\t</ul>", $body);
		$this->assertStringContainsString('name="req_subject" value="SHOUTED" size="20"', $body, 'the subject as it was submitted');
		$this->assertStringNotContainsString('post-preview', $body);

		$this->kit->visitor->lastPostAt = null;
		$this->assertStringContainsString('<li class="warn"><span>Unable to confirm security token.', $this->page(array('tid' => '2'), array('form_sent' => '1', 'form_user' => 'member', 'req_message' => 'Hi')));
		$this->assertStringContainsString('<li class="warn"><span>Bad <b>tag</b></span></li>', $this->page(array('tid' => '2'), $this->sent(array('req_message' => '[bad]'))));
		$this->assertStringContainsString('<li class="warn"><span>You must enter a message.</span></li>', $this->page(array('tid' => '2'), $this->sent(array('req_message' => '   '))));
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('tid' => '2'), $this->sent(array('form_user' => 'someone', 'req_message' => 'Hi'))), 'a form sent in another name');

		$this->kit->visitor->guest = true;
		$guest = $this->page(array('tid' => '2'), $this->sent(array('req_username' => 'x', 'email' => 'x@banned.invalid', 'req_message' => 'Hi')));
		$this->assertStringContainsString("<li class=\"warn\"><span>Username <b>too short</b></span></li>\n\t\t\t\t<li class=\"warn\"><span>The email address you entered is banned in this forum. Please choose another email address.</span></li>", $guest);
		$this->assertStringContainsString('name="req_username" value="x" size="35"', $guest);
		$this->assertStringContainsString('name="email" value="x@banned.invalid" size="35"', $guest);

		$this->assertSame(array(), $this->posting->stored);
	}

	public function testAReplyIsStoredWithTheSubscriptionItChanges(): void {
		$steps = array();
		$this->kit->events->observe(PostingStep::class, function (PostingStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.$event->postId().' '.count($this->posting->stored);
		});

		$response = $this->page(array('tid' => '2'), $this->sent(array('req_message' => ' A reply ')));

		$this->assertStringStartsWith('302 /post/31?a=1&b=2 [redirect]', $response);
		$this->assertSame(array('selected 0 0', 'submitted 0 0', 'validated 0 0', 'adding 0 0', 'added 31 1'), $steps);

		$post = $this->posting->stored[0];
		$this->assertSame(array(false, 'member', 3, null, 'Topic <2>', 'A reply', false, 2, 1, 'Forum & 1', NewPostInterface::SUBSCRIPTION_ENDED),
			array($post->isGuest(), $post->poster(), $post->posterId(), $post->posterEmail(), $post->subject(), $post->message(), $post->hidesSmilies(), $post->topicId(), $post->forumId(), $post->forumName(), $post->subscription()));
		$this->assertEqualsWithDelta(time(), $post->postedAt(), 2);
	}

	public function testATopicIsStoredAsAnObserverLeftItAndAGuestKeepsTheirAddress(): void {
		$this->kit->visitor->guest = true;
		$this->kit->visitor->id = 1;
		$this->kit->events->observe(PostingStep::class, static function (PostingStep $event): void {
			$post = $event->post();
			if ($event->step() === PostingStep::ADDING && $post !== null)
				$event->replacePost(new NewPost($post->isGuest(), $post->poster(), $post->posterId(), $post->posterEmail(), 'Replaced', $post->message(), true, $post->postedAt(), 0, $post->forumId(), $post->forumName(), $post->subscription()));
		});

		$response = $this->page(array('fid' => '1'), $this->sent(array('req_subject' => 'New topic', 'req_username' => 'visitor', 'email' => ' Visitor@Example.com ', 'req_message' => 'Hello', 'subscribe' => '1')));

		$this->assertStringStartsWith('302 /post/32?a=1&b=2 [redirect]', $response);

		$post = $this->posting->stored[0];
		$this->assertSame(array(true, 'visitor', 1, 'visitor@example.com', 'Replaced', 'Hello', true, 0, 1, NewPostInterface::SUBSCRIPTION_STARTED),
			array($post->isGuest(), $post->poster(), $post->posterId(), $post->posterEmail(), $post->subject(), $post->message(), $post->hidesSmilies(), $post->topicId(), $post->forumId(), $post->subscription()));
	}
}
