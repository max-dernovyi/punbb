<?php
/**
 * viewtopic.php as a module, built with no forum: who may read it, a post's
 * page and the redirects to new and last posts, the head and options of a
 * topic, each post with its poster and what the visitor may do with it, the
 * parts observers change, the quick reply form, and the view counted.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\TrackedTopics;
use PunBB\Module\Viewtopic\Api\Data\PostLocationInterface;
use PunBB\Module\Viewtopic\Api\Data\ViewedTopicInterface;
use PunBB\Module\Viewtopic\Api\TopicPostsInterface;
use PunBB\Module\Viewtopic\Controller\TopicController;
use PunBB\Module\Viewtopic\Event\NewPostSeeking;
use PunBB\Module\Viewtopic\Event\PostAssembling;
use PunBB\Module\Viewtopic\Event\QuickPostRendering;
use PunBB\Module\Viewtopic\Event\TopicOptionsAssembling;
use PunBB\Module\Viewtopic\Event\TopicViewEnding;
use PunBB\Module\Viewtopic\Event\TopicViewRequested;
use PunBB\Module\Viewtopic\Event\TopicViewStep;
use PunBB\Module\Viewtopic\Model\Moderator;
use PunBB\Module\Viewtopic\Model\PostLocation;
use PunBB\Module\Viewtopic\Model\TopicPost;
use PunBB\Module\Viewtopic\Model\ViewedTopic;

require_once __DIR__.'/PageFakes.php';

final class FakeTopicPosts implements TopicPostsInterface {
	public ?ViewedTopic $topic = null;

	/** @var list<TopicPost> */
	public array $posts = array();

	public ?int $firstNew = null;

	public ?int $last = null;

	/** @var list<string> */
	public array $log = array();

	public function locate(int $postId): ?PostLocationInterface {
		return $postId === 13 ? new PostLocation(4, 500) : null;
	}

	public function countBefore(int $topicId, int $posted): int {
		$this->log[] = 'before '.$topicId.' '.$posted;

		return 30;
	}

	public function firstPostAfter(int $topicId, int $after): ?int {
		$this->log[] = 'first after '.$after;

		return $this->firstNew;
	}

	public function lastPostId(int $topicId): ?int {
		return $this->last;
	}

	public function topic(int $topicId, int $groupId, ?int $subscriberId): ?ViewedTopicInterface {
		$this->log[] = 'topic '.$topicId.' '.$groupId.' '.var_export($subscriberId, true);

		return $topicId === 4 ? $this->topic : null;
	}

	public function postIds(int $topicId, int $offset, int $limit): array {
		$this->log[] = 'ids '.$offset.' '.$limit;

		return array_map(static fn (TopicPost $post): int => $post->id(), $this->posts);
	}

	public function posts(array $postIds): array {
		return $this->posts;
	}

	public function countView(int ...$topicIds): void {
		$this->log[] = 'view '.implode(',', $topicIds);
	}
}

class TopicControllerTest extends TestCase {
	private PageKit $kit;

	private FakeTopicPosts $topics;

	protected function setUp(): void {
		$this->kit = new PageKit(array(TopicViewRequested::class, NewPostSeeking::class, TopicViewStep::class, TopicOptionsAssembling::class, PostAssembling::class, TopicViewEnding::class,
			QuickPostRendering::class, MessageShowing::class, MessageRendering::class));
		$this->kit->language->real = array('topic', 'common');
		$this->kit->settings->values += array('o_subscriptions' => '1', 'o_censoring' => '1', 'o_show_user_info' => '1', 'o_show_post_count' => '0', 'o_avatars' => '1',
			'o_signatures' => '1', 'o_quickpost' => '1', 'o_topic_views' => '1', 'p_message_bbcode' => '1', 'o_smilies' => '1');
		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard, GroupPermission::ViewUsers, GroupPermission::PostReplies, GroupPermission::EditPosts, GroupPermission::DeletePosts, GroupPermission::SendEmail);
		$this->kit->visitor->postsPerPage = 2;

		$this->topics = new FakeTopicPosts();
		$this->topics->topic = new ViewedTopic(4, 'Darn <topic>', 11, false, false, 2, 1, 'Forum & 1', array(new Moderator(7, 'mod')), null, true);
		$this->topics->posts = array(
			self::post(11, 3, 'member', '<b>first</b>', online: true, signature: 'Sig <1>', avatar: 2),
			self::post(12, 1, 'Visitor <v>', 'guest post', email: 'v@example.com'),
			self::post(13, 3, 'member', 'again', edited: 700),
		);
	}

	private static function post(int $id, int $posterId, string $poster, string $message, bool $online = false, ?string $signature = null, int $avatar = 0, ?string $email = null, ?int $edited = null): TopicPost {
		return new TopicPost($id, $posterId, $poster, '192.0.2.'.$posterId, $email, $message, false, 100 * $id, $edited, $edited !== null ? 'mod<x>' : null,
			$posterId.'@example.com', null, $posterId > 1 ? 'http://example.com/'.$posterId : null, $posterId > 1 ? 'Darn town' : null, $signature, 0, 12, 50, $posterId > 1 ? 'Watched' : null, $avatar, 60, 40, $posterId > 1 ? 3 : 2, null, $online);
	}

	private function page(array $query): string {
		$controller = new TopicController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->topics,
			$this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter, $this->kit->tokens);

		$response = $controller->handle(new Request('GET', '/', 'viewtopic.php', $query));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	public function testAVisitorWhoMayNotReadGetsAMessageAndAMissingTopicIsABadRequest(): void {
		$this->kit->visitor->permissions = array();
		$this->assertStringContainsString('<p>You do not have permission to view these forums.</p>', $this->page(array('id' => '4')));

		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard);
		foreach (array(array(), array('id' => '0', 'pid' => '0'), array('id' => '9'), array('pid' => '99'), array('id' => array('4'))) as $query)
			$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page($query));
	}

	public function testAPostIsShownOnItsPageWhichIsNotIndexed(): void {
		$this->page(array('pid' => '13'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array(false, 1, '(Page 1 of 2)'), array($head->indexable, $head->page, $head->pageCount?->html), '31 posts up to it, two a page: past the last page is the first');
		$this->assertSame(array('before 4 500', 'topic 4 3 3', 'ids 0 2', 'view 4'), $this->topics->log);
	}

	public function testNewAndLastPostsSendTheVisitorOn(): void {
		$this->kit->visitor->tracked = new TrackedTopics(array(4 => 800));
		$this->topics->firstNew = 12;

		$seeking = array();
		$this->kit->events->observe(NewPostSeeking::class, function (NewPostSeeking $event) use (&$seeking): void {
			$seeking[] = $event->topicId().' '.$event->lastViewed();
		});

		$this->assertSame('302 /post/12?a=1&b=2 ', $this->page(array('id' => '4', 'action' => 'new')));
		$this->topics->firstNew = null;
		$this->assertSame('302 /topic_last_post/5?a=1&b=2 ', $this->page(array('id' => '5', 'action' => 'new')));
		$this->assertSame(array('4 800', '5 1000'), $seeking, 'a topic not read since the last visit is sought from the visit');

		$this->kit->visitor->guest = true;
		$this->assertSame('302 /topic_last_post/4?a=1&b=2 ', $this->page(array('id' => '4', 'action' => 'new')));
		$this->assertCount(2, $seeking);

		$this->topics->last = 13;
		$this->assertSame('302 /post/13?a=1&b=2 ', $this->page(array('id' => '4', 'action' => 'last')));
		$this->topics->last = null;
		$this->assertStringStartsWith('200 ', $this->page(array('id' => '4', 'action' => 'last')));
		$this->assertSame(array(), $this->kit->visitor->read === array() ? array() : array('a guest reads nothing'));
	}

	public function testTheHeadCarriesTheCensoredTopicItsPagingAndWhereToReply(): void {
		$this->page(array('id' => '4', 'p' => '2'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('viewtopic', true, 2), array($head->id, $head->indexable, $head->page));
		$this->assertSame(array('Board & Co', 'Forum & 1', 'd*rn <topic>'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));
		$this->assertSame('<a class="permalink" href="/topic/4/slug-d-rn-topic-?a=1&amp;b=2" rel="bookmark" title="Permanent link to this topic">d*rn &lt;topic&gt;</a>', $head->mainTitle?->html);
		$this->assertSame(array('prev' => '<link rel="prev" href="/topic/4/slug-darn-topic-~page=1" title="Page 1" />', 'first' => '<link rel="first" href="/topic/4/slug-darn-topic-?a=1&amp;b=2" title="Page 1" />'),
			array_map(static fn ($link): string => $link->html, $head->navigation), 'the links in the head are built before the subject is censored');
		$this->assertSame('<p class="paging"><span class="pages">Pages</span> [pages 2 of 2 at topic]</p>', $head->pagePost['paging']->html);
		$this->assertSame('<p class="posting"><a class="newpost" href="/new_reply/4?a=1&amp;b=2"><span>Post reply</span></a></p>', $head->pagePost['posting']->html);
		$this->assertSame(array(4 => $this->kit->visitor->read[4]), $this->kit->visitor->read);

		$this->topics->topic = new ViewedTopic(4, 'Shut', 11, true, false, 0, 1, 'Forum & 1', array(), null, false);
		$this->page(array('id' => '4'));
		$head = $this->kit->chromes->opened[1];
		$this->assertStringStartsWith('[ Closed ] <a class="permalink"', (string) $head->mainTitle?->html);
		$this->assertSame('<p class="posting">This topic is closed</p>', $head->pagePost['posting']->html);
	}

	public function testThePostsShowTheirPostersAndWhatAMemberMayDoWithThem(): void {
		$body = $this->page(array('id' => '4'));

		$this->assertStringContainsString("<p class=\"options\"><span class=\"feed first-item\"><a class=\"feed\" href=\"/topic_rss/4?a=1&amp;b=2\">RSS topic feed</a></span> <span><a class=\"sub-option\" href=\"/unsubscribe/4/token-for-".md5('unsubscribe43')."?a=1&amp;b=2\"><em>Unsubscribe</em></a></span></p>\n\t\t<h2 class=\"hn\"><span>Posts: 1-2/3 in 2</span></h2>", $body);
		$this->assertStringContainsString('<div id="forum1" class="main-content main-topic">', $body);

		$this->assertStringContainsString("<div class=\"post odd firstpost topicpost\">\n\t\t\t<div id=\"p11\" class=\"posthead\">\n\t\t\t\t<h3 class=\"hn post-ident\"><span class=\"post-num\">1</span> <span class=\"post-byline\"><span>Topic by </span><a title=\"Go to member's profile\" href=\"/user/3?a=1&amp;b=2\">member</a></span> <span class=\"post-link\"><a class=\"permalink\" rel=\"bookmark\" title=\"Permanent link to this post\" href=\"/post/11?a=1&amp;b=2\"><time>1100</time></a></span></h3>", $body);
		$this->assertStringContainsString("<div class=\"postbody online\">\n\t\t\t\t<div class=\"post-author\">\n\t\t\t\t\t<ul class=\"author-ident\">\n\t\t\t\t\t\t<li class=\"useravatar\"><img src=\"avatar/3\" alt=\"member\" /></li>\n\t\t\t\t\t\t<li class=\"username\"><a title=\"Go to member's profile\" href=\"/user/3?a=1&amp;b=2\">member</a></li>\n\t\t\t\t\t\t<li class=\"usertitle\"><span>[title of member]</span></li>\n\t\t\t\t\t\t<li class=\"userstatus\"><span>Online</span></li>\n\t\t\t\t\t</ul>", $body);
		$this->assertStringContainsString("<ul class=\"author-info\">\n\t\t\t\t\t\t<li><span>From: <strong>d*rn town</strong></span></li>\n\t\t\t\t\t\t<li><span>Registered: <strong><time>50</time></strong></span></li>\n\t\t\t\t\t</ul>", $body);
		$this->assertStringContainsString("<h4 id=\"pc11\" class=\"entry-title hn\">Topic: d*rn &lt;topic&gt;</h4>\n\t\t\t\t\t<div class=\"entry-content\">\n\t\t\t\t\t\t<p>&lt;b&gt;first&lt;/b&gt;</p>\n\t\t\t\t\t\t<div class=\"sig-content\"><span class=\"sig-line\"><!-- --></span><em>Sig &lt;1&gt;</em></div>", $body);
		$this->assertStringContainsString('<p class="post-contacts"><span class="user-url first-item"><a class="external" href="http://example.com/3"><span>member\'s</span> Website</a></span> <span class="user-email"><a href="mailto:3@example.com">Email<span>&#160;member</span></a></span></p>', $body);
		$this->assertStringContainsString('<p class="post-actions"><span class="report-post first-item"><a href="/report/11?a=1&amp;b=2">Report<span> Post 1</span></a></span> <span class="edit-post"><a href="/edit/11?a=1&amp;b=2">Edit<span> Post 1</span></a></span> <span class="quote-post"><a href="/quote/4/11?a=1&amp;b=2">Quote<span> Post 1</span></a></span></p>', $body);

		$this->assertStringContainsString('<div class="post even lastpost replypost">', $body);
		$this->assertStringContainsString("<ul class=\"author-ident\">\n\t\t\t\t\t\t<li class=\"username\"><strong>Visitor &lt;v&gt;</strong></li>\n\t\t\t\t\t\t<li class=\"usertitle\"><span>[title of Visitor &lt;v&gt;]</span></li>\n\t\t\t\t\t</ul>\n\t\t\t\t\t<ul class=\"author-info\">\n\t\t\t\t\t\t\n\t\t\t\t\t</ul>", $body);
		$this->assertStringContainsString('<p class="post-actions"><span class="report-post first-item"><a href="/report/12?a=1&amp;b=2">Report<span> Post 2</span></a></span> <span class="quote-post"><a href="/quote/4/12?a=1&amp;b=2">Quote<span> Post 2</span></a></span></p>', $body);
		$this->assertStringNotContainsString('mailto:v@example.com', $body, 'only a moderator sees a guest\'s address');

		$this->assertStringContainsString("<div class=\"post odd replypost\">\n\t\t\t<div id=\"p13\" class=\"posthead\">", $body, 'a third post past the page\'s last number is not the last');
		$this->assertStringContainsString('<span class="post-edit">(edited by mod&lt;x&gt; <time>700</time>)</span>', $body);
		$this->assertStringNotContainsString('<a href="/delete/11', $body, 'a member does not delete the topic by its first post without the permission');
		$this->assertStringNotContainsString('<a href="/delete/12', $body, 'nor another\'s post');
		$this->assertStringContainsString('<span class="delete-post"><a href="/delete/13?a=1&amp;b=2">Delete<span> Post 3</span></a></span>', $body);
		$this->assertStringContainsString("<div class=\"main-foot\">\n\t\t<h2 class=\"hn\"><span>Posts: 1-2/3 in 2</span></h2>\n\t</div>", $body);
		$this->assertSame(array('topic 4 3 3', 'ids 0 2', 'view 4'), $this->topics->log);
	}

	public function testAModeratorGetsTheOptionsBelowTheAddressesAndEveryAction(): void {
		$this->kit->visitor->permissions[] = GroupPermission::Moderate;
		$this->kit->visitor->moderating = true;
		$this->topics->topic = new ViewedTopic(4, 'Topic', 11, true, true, 2, 1, 'Forum & 1', array(new Moderator(3, 'member')), false, false);

		$body = $this->page(array('id' => '4'));

		$userId = $this->kit->visitor->id();
		$this->assertStringContainsString("<div class=\"main-foot\">\n\n\t\t\t<p class=\"options\"><span class=\"first-item\"><a class=\"mod-option\" href=\"/move/1/4?a=1&amp;b=2\">Move topic</a></span> <span><a class=\"mod-option\" href=\"/delete/11?a=1&amp;b=2\">Delete topic</a></span> <span><a class=\"mod-option\" href=\"/open/1/4/token-for-".md5('open4'.$userId)."?a=1&amp;b=2\">Open topic</a></span> <span><a class=\"mod-option\" href=\"/unstick/1/4/token-for-".md5('unstick4'.$userId)."?a=1&amp;b=2\">Unstick topic</a></span> <span><a class=\"mod-option\" href=\"/moderate_topic/1/4~page=1\">Moderate topic</a></span></p>\t\t<h2 class=\"hn\">", $body);
		$this->assertStringContainsString('<li><span>Posts: <strong>12</strong></span></li>'."\n\t\t\t\t\t\t".'<li><span>Note: <strong>Watched</strong></span></li>'."\n\t\t\t\t\t\t".'<li><span>IP: <a href="/get_host/11?a=1&amp;b=2">192.0.2.3</a></span></li>', $body);
		$this->assertStringContainsString('<span class="delete-topic"><a href="/delete/11?a=1&amp;b=2">Delete topic</a></span>', $body);
		$this->assertStringContainsString('<span class="delete-post"><a href="/delete/12?a=1&amp;b=2">Delete<span> Post 2</span></a></span>', $body);
		$this->assertStringContainsString('<p class="post-contacts"><span class="user-email first-item"><a href="mailto:v@example.com">Email<span>&#160;Visitor &lt;v&gt;</span></a></span></p>', $body);
		$this->assertStringContainsString('<p class="posting"><a class="newpost"', $this->kit->chromes->opened[0]->pagePost['posting']->html, 'a moderator replies to a closed topic');
		$this->assertStringNotContainsString('brd-qpost', $body, 'the forum forbids the group to reply, so no quick reply even for its moderator');
	}

	public function testAKeptPosterIsBuiltOnceAndObserversChangeThePosts(): void {
		$stages = array();
		$this->kit->events->observe(PostAssembling::class, function (PostAssembling $event) use (&$stages): void {
			$stages[] = $event->stage().' '.$event->post()->id().' '.$event->number().'/'.$event->itemCount();

			if ($event->stage() === PostAssembling::ROW && $event->post()->id() === 11)
			{
				$event->set(PostAssembling::PART_ITEM_STATUS, 'probe', 'probed');
				$event->setSubject('<em>probed</em>');
				$event->count($event->itemCount() + 1);
			}

			if ($event->stage() === PostAssembling::CACHED)
				$event->set(PostAssembling::PART_AUTHOR_INFO, 'kept', '<li>kept</li>');

			if ($event->stage() === PostAssembling::ENTRY)
			{
				$event->append('<p class="entry">'.$event->post()->id().'</p>'."\n");
				$event->remove(PostAssembling::PART_POST_OPTIONS, 'contacts');
			}
		});
		$this->kit->events->observe(TopicOptionsAssembling::class, function (TopicOptionsAssembling $event): void {
			$event->set(TopicOptionsAssembling::HEAD_OPTIONS, 'probe', '<span>probe</span>');
			$event->append('<!-- options -->');
		});
		$this->kit->events->observe(TopicViewEnding::class, fn (TopicViewEnding $event) => $event->append('<!-- end -->'));

		$body = $this->page(array('id' => '4'));

		$this->assertSame(array('start 11 1/0', 'ident 11 1/1', 'contacts 11 1/1', 'actions 11 1/1', 'row 11 1/1', 'cached 11 2/2', 'entry 11 2/2',
			'start 12 3/2', 'ident 12 3/3', 'contacts 12 3/3', 'actions 12 3/3', 'row 12 3/3', 'entry 12 3/3',
			'start 13 4/3', 'ident 13 4/4', 'contacts 13 4/4', 'actions 13 4/4', 'row 13 4/4', 'entry 13 4/4'), $stages);
		$this->assertStringStartsWith("200  [viewtopic]<!-- options -->\t<div class=\"main-head\">\n\t\t<p class=\"options\"><span class=\"feed first-item\">", $body);
		$this->assertStringContainsString('<span>probe</span></p>', $body);
		$this->assertStringContainsString('<div class="post odd firstpost topicpost probed">', $body);
		$this->assertStringContainsString('<h4 id="pc11" class="entry-title hn"><em>probed</em></h4>', $body);
		$this->assertStringContainsString("</div>\n<p class=\"entry\">11</p>\n\t\t\t\t</div>", $body);
		$this->assertStringNotContainsString('post-contacts', $body);
		$this->assertStringContainsString('<div class="post odd replypost">', $body, 'the next post counts on from the count an observer left');
		$this->assertStringContainsString("<li><span>Registered: <strong><time>50</time></strong></span></li>\n\t\t\t\t\t\t<li>kept</li>\n\t\t\t\t\t</ul>", $body, 'the poster\'s next post takes the parts kept');
		$this->assertStringContainsString("<h2 class=\"hn\"><span>Posts: 1-2/3 in 2</span></h2>\n\t</div>\n<!-- end -->[qpost]", $body);
	}

	public function testAGuestIsAskedToLogInAndQuotesAnOpenTopic(): void {
		$this->kit->visitor->guest = true;
		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard, GroupPermission::PostReplies);

		$body = $this->page(array('id' => '4'));

		$this->assertSame('<p class="posting"><a class="newpost" href="/new_reply/4?a=1&amp;b=2"><span>Post reply</span></a></p>', $this->kit->chromes->opened[0]->pagePost['posting']->html);
		$this->assertStringContainsString('<p class="post-actions"><span class="report-post first-item"><a href="/quote/4/11?a=1&amp;b=2">Quote<span> Post 1</span></a></span></p>', $body);
		$this->assertStringNotContainsString('sub-option', $body);
		$this->assertStringNotContainsString('brd-qpost', $body);
		$this->assertSame(array(), $this->kit->visitor->read);

		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard);
		$this->page(array('id' => '4'));
		$this->assertSame('<p class="posting">You must <a href="/login?a=1&amp;b=2">login</a> or <a href="/register?a=1&amp;b=2">register</a> to post a reply</p>', $this->kit->chromes->opened[1]->pagePost['posting']->html);
	}

	public function testTheQuickReplyFormFollowsThePostsWithWhatObserversChange(): void {
		$this->kit->visitor->autoNotify = true;
		$this->kit->events->observe(QuickPostRendering::class, function (QuickPostRendering $event): void {
			if ($event->position() === QuickPostRendering::PRE_DISPLAY)
			{
				$event->set(QuickPostRendering::HIDDEN_FIELDS, 'probe', '<input type="hidden" name="probe" />');
				$event->set(QuickPostRendering::FORM_ATTRIBUTES, 'probe', 'data-probe="1"');
			}

			if ($event->position() === QuickPostRendering::PRE_MESSAGE_BOX)
				$event->append('<!-- box -->');
		});

		$body = $this->page(array('id' => '4'));

		$action = '/new_reply/4?a=1&amp;b=2';
		$this->assertStringContainsString("<div class=\"main-subhead\">\n\t<h2 class=\"hn\"><span>Quick reply to this topic</span></h2>\n</div>\n<div id=\"brd-qpost\" class=\"main-content main-frm\">\n\t<p class=\"content-options options\">You may use: <span class=\"first-item\"><a class=\"exthelp\" href=\"/help/bbcode?a=1&amp;b=2\" title=\"Help with: BBCode\">BBCode</a></span> <span><a class=\"exthelp\" href=\"/help/smilies?a=1&amp;b=2\" title=\"Help with: Smilies\">Smilies</a></span></p>", $body);
		$this->assertStringContainsString("action=\"$action\" data-probe=\"1\">\n\t\t<div class=\"hidden\">\n\t\t\t<input type=\"hidden\" name=\"form_sent\" value=\"1\" />\n\t\t\t\t<input type=\"hidden\" name=\"form_user\" value=\"member\" />\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5($action)."\" />\n\t\t\t\t<input type=\"hidden\" name=\"subscribe\" value=\"1\" />\n\t\t\t\t<input type=\"hidden\" name=\"probe\" />\n\t\t</div>", $body);
		$this->assertStringContainsString("<!-- box -->\t\t\t<div class=\"txt-set set1\">", $body);
		$this->assertSame(array('QuickPostRendering:output_start', 'QuickPostRendering:pre_display', 'QuickPostRendering:pre_fieldset', 'QuickPostRendering:pre_message_box',
			'QuickPostRendering:pre_fieldset_end', 'QuickPostRendering:fieldset_end', 'QuickPostRendering:end'),
			array_values(array_filter($this->kit->events->dispatched, static fn (string $event): bool => str_starts_with($event, 'QuickPost'))));
		$this->assertSame(array('topic 4 3 3', 'ids 0 2', 'view 4'), $this->topics->log, 'the view is counted once the page is built');

		$this->kit->settings->values['o_topic_views'] = '0';
		$this->kit->settings->values['o_quickpost'] = '0';
		$this->assertStringNotContainsString('brd-qpost', $this->page(array('id' => '4')));
		$this->assertNotContains('view 4', array_slice($this->topics->log, 3));
	}
}
