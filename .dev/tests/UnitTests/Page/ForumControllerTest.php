<?php
/**
 * viewforum.php as a module, built with no forum: who may read a forum, a
 * forum on another site, the head of the page and its paging, the options
 * above and below the list, each kind of topic row, the row saying a forum is
 * empty, and what observers change on the way.
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
use PunBB\Module\Viewforum\Api\Data\ViewedForumInterface;
use PunBB\Module\Viewforum\Api\ForumTopicsInterface;
use PunBB\Module\Viewforum\Controller\ForumController;
use PunBB\Module\Viewforum\Event\EmptyForumAssembling;
use PunBB\Module\Viewforum\Event\ForumViewEnding;
use PunBB\Module\Viewforum\Event\ForumViewRequested;
use PunBB\Module\Viewforum\Event\ForumViewStep;
use PunBB\Module\Viewforum\Event\TopicListHeadAssembling;
use PunBB\Module\Viewforum\Event\TopicRowAssembling;
use PunBB\Module\Viewforum\Event\TopicsListing;
use PunBB\Module\Viewforum\Model\ListedTopic;
use PunBB\Module\Viewforum\Model\Moderator;
use PunBB\Module\Viewforum\Model\ViewedForum;

require_once __DIR__.'/PageFakes.php';

final class FakeForumTopics implements ForumTopicsInterface {
	public ?ViewedForum $forum = null;

	/** @var list<ListedTopic> */
	public array $topics = array();

	/** @var list<string> */
	public array $log = array();

	public function forum(int $forumId, int $groupId, ?int $subscriberId): ?ViewedForumInterface {
		$this->log[] = 'forum '.$forumId.' '.$groupId.' '.var_export($subscriberId, true);

		return $this->forum?->id() === $forumId ? $this->forum : null;
	}

	public function topicIds(int $forumId, bool $byPosted, int $offset, int $limit): array {
		$this->log[] = 'ids '.$forumId.' '.(int) $byPosted.' '.$offset.' '.$limit;

		return array_map(static fn (ListedTopic $topic): int => $topic->id(), array_slice($this->topics, $offset, $limit));
	}

	public function topics(array $topicIds, bool $byPosted, ?int $posterId): array {
		$this->log[] = 'topics '.implode(',', $topicIds).' '.var_export($posterId, true);

		return array_values(array_filter($this->topics, static fn (ListedTopic $topic): bool => in_array($topic->id(), $topicIds, true)));
	}
}

class ForumControllerTest extends TestCase {
	private PageKit $kit;

	private FakeForumTopics $forums;

	protected function setUp(): void {
		$this->kit = new PageKit(array(ForumViewRequested::class, ForumViewStep::class, TopicListHeadAssembling::class, TopicsListing::class, TopicRowAssembling::class,
			EmptyForumAssembling::class, ForumViewEnding::class, MessageShowing::class, MessageRendering::class));
		$this->kit->language->real = array('forum', 'common');
		$this->kit->settings->values += array('o_subscriptions' => '1', 'o_topic_views' => '1', 'o_show_dot' => '0', 'o_censoring' => '0');
		$this->kit->visitor->permissions[] = GroupPermission::PostTopics;
		$this->kit->visitor->topicsPerPage = 3;
		$this->kit->visitor->postsPerPage = 2;
		$this->kit->visitor->lastVisit = 500;

		$this->forums = new FakeForumTopics();
		$this->forums->forum = new ViewedForum(4, 'Forum <4>', 'Desc', '', array(new Moderator(8, 'mod')), 4, false, null, false);
		$this->forums->topics = array(
			new ListedTopic(11, 'poster<1>', 'Sticky & closed', 100, 20, 900, 25, 'last<1>', 1, 1, true, true, null, true),
			new ListedTopic(12, 'poster2', 'Moved away', 200, 30, 300, 31, 'last2', 0, 0, false, false, 15, false),
			new ListedTopic(13, 'poster3', 'Darn long', 300, 40, 400, 45, 'last3', 7, 4, false, false, null, false),
			new ListedTopic(14, 'poster4', 'On page two', 50, 50, 60, 55, 'last4', 0, 0, false, false, null, false),
		);
	}

	private function page(array $query): string {
		$controller = new ForumController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->forums,
			$this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter, $this->kit->tokens);

		$response = $controller->handle(new Request('GET', '/', 'viewforum.php', $query));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	public function testAVisitorWhoMayNotReadTheBoardGetsAMessage(): void {
		$this->kit->visitor->permissions = array();

		$this->assertStringContainsString('<p>You do not have permission to view these forums.</p>', $this->page(array('id' => '4')));
		$this->assertSame(array(), $this->forums->log);
	}

	public function testAForumThatIsNotThereOrNotReadableIsABadRequest(): void {
		foreach (array(array(), array('id' => '0'), array('id' => 'x'), array('id' => array('4')), array('id' => '9')) as $query)
			$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page($query));

		$this->assertSame(array('forum 9 3 3'), $this->forums->log);
	}

	public function testAForumOnAnotherSiteSendsTheVisitorThere(): void {
		$this->forums->forum = new ViewedForum(4, 'Elsewhere', '', 'http://example.com/', array(), 0, false, null, false);

		$this->assertSame('302 http://example.com/ ', $this->page(array('id' => '4')));
		$this->assertSame(array('ForumViewRequested', 'ForumViewStep', 'ForumViewStep'), $this->kit->events->dispatched);
		$this->assertSame(array(), $this->kit->chromes->opened);
	}

	public function testTheHeadCarriesTheForumItsPagingAndWhereToPost(): void {
		$this->page(array('id' => '4'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('viewforum', true, 1), array($head->id, $head->indexable, $head->page));
		$this->assertSame(array('Board & Co', 'Forum <4>'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));
		$this->assertNull($head->crumbs[1]->link);
		$this->assertSame('<a class="permalink" href="/forum/4/slug-forum-4-?a=1&amp;b=2" rel="bookmark" title="Permanent link to this forum.">Forum &lt;4&gt;</a>', $head->mainTitle?->html);
		$this->assertSame('(Page 1 of 2)', $head->pageCount?->html);
		$this->assertSame(array('last' => '<link rel="last" href="/forum/4/slug-forum-4-~page=2" title="Page 2" />', 'next' => '<link rel="next" href="/forum/4/slug-forum-4-~page=2" title="Page 2" />'),
			array_map(static fn ($link): string => $link->html, $head->navigation));
		$this->assertSame('<p class="paging"><span class="pages">Pages</span> [pages 1 of 2 at forum]</p>', $head->pagePost['paging']->html);
		$this->assertSame('<p class="posting"><a class="newpost" href="/new_topic/4?a=1&amp;b=2"><span>Post new topic</span></a></p>', $head->pagePost['posting']->html);
		$this->assertSame(array('forum 4 3 3', 'ids 4 0 0 3', 'topics 11,12,13 NULL'), $this->forums->log);
	}

	public function testAGuestIsAskedToLogInAndAMemberWithoutPermissionIsToldSo(): void {
		$this->kit->visitor->guest = true;
		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard);
		$this->page(array('id' => '4'));
		$this->assertSame('<p class="posting">You must <a href="/login?a=1&amp;b=2">login</a> or <a href="/register?a=1&amp;b=2">register</a> to post a new topic</p>', $this->kit->chromes->opened[0]->pagePost['posting']->html);
		$this->assertSame('forum 4 3 NULL', $this->forums->log[0]);

		$this->kit->visitor->guest = false;
		$this->page(array('id' => '4'));
		$this->assertSame('<p class="posting">Sorry! no permission to post new topics</p>', $this->kit->chromes->opened[1]->pagePost['posting']->html);

		$this->forums->forum = new ViewedForum(4, 'Forum <4>', '', '', array(), 4, false, true, false);
		$this->page(array('id' => '4'));
		$this->assertStringContainsString('class="newpost"', $this->kit->chromes->opened[2]->pagePost['posting']->html, 'the forum lets the group post');
	}

	public function testTheListShowsEachKindOfTopic(): void {
		$this->kit->settings->values['o_show_dot'] = '1';
		$this->kit->visitor->tracked = new TrackedTopics(array(13 => 450));

		$body = $this->page(array('id' => '4'));

		$this->assertStringStartsWith("200  [viewforum]<div class=\"main-head\">\n\n\t\t<p class=\"options\"><span class=\"feed first-item\"><a class=\"feed\" href=\"/forum_rss/4?a=1&amp;b=2\">RSS forum feed</a></span> <span><a class=\"sub-option\" href=\"/forum_subscribe/4/token-for-".md5('forum_subscribe43')."?a=1&amp;b=2\" title=\"Receive email notification of new topics.\">Subscribe</a></span></p>\t\t<h2 class=\"hn\"><span>Topics: 1-3/4 in 2</span></h2>", $body);
		$this->assertStringContainsString('<p class="item-summary forum-views"><span><strong class="subject-title">Topics</strong> in this forum with details of <strong class="info-replies">replies</strong>, <strong class="info-views">views</strong>, <strong class="info-lastpost">last post</strong>.</span></p>', $body);

		$this->assertStringContainsString("<div id=\"forum4\" class=\"main-content main-forum forum-views\">\n\t\t<div id=\"topic11\" class=\"main-item odd main-first-item posted sticky closed new\">\n\t\t\t<span class=\"icon posted sticky closed new\"><!-- --></span>", $body);
		$this->assertStringContainsString('<h3 class="hn"><span class="item-num">1</span> <span class="posted-mark">·</span> <span class="item-status"><em class="sticky">Sticky</em>, <em class="closed">Closed</em>:</span> <a href="/topic/11/slug-sticky-closed?a=1&amp;b=2">Sticky &amp; closed</a></h3>', $body);
		$this->assertStringContainsString("<p><span class=\"item-starter\">by <cite>poster&lt;1&gt;</cite></span> <span class=\"item-nav\">( <em class=\"item-newposts\"><a href=\"/topic_new_posts/11/slug-sticky-closed?a=1&amp;b=2\">New posts</a></em> )</span></p>", $body);
		$this->assertStringContainsString("<li class=\"info-replies\"><strong>1</strong> <span class=\"label\">reply</span></li>\n\t\t\t\t<li class=\"info-views\"><strong>1</strong> <span class=\"label\">view</span></li>\n\t\t\t\t<li class=\"info-lastpost\"><span class=\"label\">Last post</span> <strong><a href=\"/post/25?a=1&amp;b=2\"><time>900</time></a></strong> <cite>by last&lt;1&gt;</cite></li>", $body);

		$this->assertStringContainsString("<div id=\"topic12\" class=\"main-item even moved\">", $body);
		$this->assertStringContainsString('<h3 class="hn"><span class="item-num">2</span><span class="item-status"><em class="moved">Moved:</em></span> <a href="/topic/15/slug-moved-away?a=1&amp;b=2">Moved away</a></h3>', $body);
		$this->assertStringContainsString("<li class=\"info-replies\"><span class=\"label\">No reply information</span></li>\n\t\t\t\t<li class=\"info-views\"><span class=\"label\">No viewing information</span></li>", $body);

		$this->assertStringContainsString("<div id=\"topic13\" class=\"main-item odd normal\">", $body, 'read since its last post');
		$this->assertStringContainsString('<span class="item-nav">( <span>Pages&#160;</span>[pages -1 of 3 at topic by &#160;] )</span>', $body);

		$this->assertStringEndsWith("\t<div class=\"main-foot\">\n\n\t\t\t<p class=\"options\"><span class=\"first-item\"><a href=\"/mark_forum_read/4/token-for-".md5('markforumread43')."?a=1&amp;b=2\">Mark forum as read</a></span></p>\t\t<h2 class=\"hn\"><span>Topics: 1-3/4 in 2</span></h2>\n\t</div>", $body);
		$this->assertSame(array('forum 4 3 3', 'ids 4 0 0 3', 'topics 11,12,13 3'), $this->forums->log);
	}

	public function testAModeratorMayModerateAndASubscriberUnsubscribe(): void {
		$this->kit->visitor->permissions[] = GroupPermission::Moderate;
		$this->forums->forum = new ViewedForum(4, 'Forum <4>', '', '', array(new Moderator(3, 'member')), 4, true, false, true);

		$body = $this->page(array('id' => '4', 'p' => '2'));

		$this->assertStringContainsString('<span><a class="sub-option" href="/forum_unsubscribe/4/token-for-'.md5('forum_unsubscribe43').'?a=1&amp;b=2"><em>Unsubscribe</em></a></span></p>', $body);
		$this->assertStringContainsString('<span><a href="/moderate_forum/4~page=2">Moderate forum</a></span></p>', $body);
		$this->assertStringContainsString('<div id="topic14" class="main-item odd main-first-item normal">', $body);
		$this->assertStringContainsString('<span class="item-num">4</span>', $body);
		$this->assertSame(array('prev', 'first'), array_keys($this->kit->chromes->opened[0]->navigation));
		$this->assertSame(array('forum 4 3 3', 'ids 4 1 3 3', 'topics 14 NULL'), $this->forums->log, 'sorted by when posted, from the second page');
		$this->assertStringContainsString('class="newpost"', $this->kit->chromes->opened[0]->pagePost['posting']->html, 'a moderator posts where the group may not');
	}

	public function testAGuestGetsNeitherSubscriptionsNorFootOptions(): void {
		$this->kit->visitor->guest = true;
		$this->kit->settings->values['o_topic_views'] = '0';
		$this->kit->settings->values['o_censoring'] = '1';

		$body = $this->page(array('id' => '4'));

		$this->assertStringContainsString("<div class=\"main-head\">\n\n\t\t<p class=\"options\"><span class=\"feed first-item\"><a class=\"feed\" href=\"/forum_rss/4?a=1&amp;b=2\">RSS forum feed</a></span></p>\t\t<h2", $body);
		$this->assertStringContainsString("<div class=\"main-foot\">\n\t\t<h2 class=\"hn\">", $body);
		$this->assertStringContainsString('<p class="item-summary forum-noview"><span><strong class="subject-title">Topics</strong> in this forum with details of <strong class="info-replies">replies</strong>, <strong class="info-lastpost">last post</strong>.</span></p>', $body);
		$this->assertStringNotContainsString('info-views', substr($body, (int) strpos($body, '<div id="forum4"')));
		$this->assertStringContainsString('<a href="/topic/13/slug-d-rn-long?a=1&amp;b=2">d*rn long</a>', $body, 'the subject is censored');
		$this->assertStringNotContainsString('item-newposts', $body);
	}

	public function testAnEmptyForumSaysSo(): void {
		$this->forums->topics = array();
		$this->forums->forum = new ViewedForum(4, 'Forum <4>', '', '', array(), 0, false, null, false);
		$this->kit->events->observe(EmptyForumAssembling::class, function (EmptyForumAssembling $event): void {
			$event->set('probe', '<p>probed</p>');
			$event->append('<!-- empty -->');
		});

		$body = $this->page(array('id' => '4'));

		$this->assertSame("200  [viewforum]<!-- empty -->\t<div class=\"main-head\">\n\n\t\t<p class=\"options\"><span><a class=\"sub-option\" href=\"/forum_subscribe/4/token-for-".md5('forum_subscribe43')."?a=1&amp;b=2\" title=\"Receive email notification of new topics.\">Subscribe</a></span></p>\t\t<h2 class=\"hn\"><span>Empty forum</span></h2>\n".
			"\t</div>\n\t<div id=\"forum4\" class=\"main-content main-forum\">\n\t\t<div class=\"main-item empty main-first-item\">\n\t\t\t<span class=\"icon empty\"><!-- --></span>\n\t\t\t<div class=\"item-subject\">\n".
			"\t\t\t\t<h3 class=\"hn\">No topics have been posted</h3>\n\t\t\t\t<p>Be the first to post a topic in this forum.</p>\n\t\t\t\t<p>probed</p>\n\t\t\t</div>\n\t\t</div>\n\t</div>\n".
			"\t<div class=\"main-foot\">\n\t\t<h2 class=\"hn\"><span>Empty forum</span></h2>\n\t</div>", $body);
		$this->assertSame(array('forum 4 3 3', 'ids 4 0 0 3'), $this->forums->log);
		$this->assertSame(array(), $this->kit->chromes->opened[0]->navigation);
	}

	public function testObserversChangeTheListItsRowsAndTheirCount(): void {
		$stages = array();
		$this->kit->events->observe(TopicListHeadAssembling::class, function (TopicListHeadAssembling $event): void {
			$event->remove(TopicListHeadAssembling::HEAD_OPTIONS, 'feed');
			$event->set(TopicListHeadAssembling::INFO, 'probe', '<strong>probe</strong>');
			$event->append('<!-- head -->');
		});
		$this->kit->events->observe(TopicsListing::class, function (TopicsListing $event): void {
			$event->keep(array(13, 11, 99));
			$event->append('<!-- listing -->');
		});
		$this->kit->events->observe(TopicRowAssembling::class, function (TopicRowAssembling $event) use (&$stages): void {
			$stages[] = $event->topic()->id().':'.$event->stage().':'.$event->number().':'.$event->itemCount();

			if ($event->stage() === TopicRowAssembling::START && $event->topic()->id() === 13)
			{
				$event->append('<div class="main-item odd">extra</div>');
				$event->count($event->itemCount() + 1);
			}

			if ($event->stage() === TopicRowAssembling::ROW)
				$event->setStyle($event->style().' probed');

			if ($event->stage() === TopicRowAssembling::SUBJECT)
				$event->set(TopicRowAssembling::PART_BODY_INFO, 'probe', '<li>probe</li>');
		});
		$this->kit->events->observe(ForumViewEnding::class, fn (ForumViewEnding $event) => $event->append('<!-- end -->'));

		$body = $this->page(array('id' => '4'));

		$this->assertStringStartsWith("200  [viewforum]<!-- head -->\t<div class=\"main-head\">\n\n\t\t<p class=\"options\"><span><a class=\"sub-option\"", $body);
		$this->assertStringContainsString('<strong class="info-lastpost">last post</strong>, <strong>probe</strong>.</span>', $body);
		$this->assertStringContainsString("<!-- listing -->\t\t<div id=\"topic11\" class=\"main-item odd main-first-item sticky closed new probed\">", $body);
		$this->assertStringContainsString("<li>probe</li>\n\t\t\t</ul>\n\t\t</div>\n<div class=\"main-item odd\">extra</div>\t\t<div id=\"topic13\" class=\"main-item odd normal probed\">", $body);
		$this->assertStringContainsString('<span class="item-num">3</span> <a href="/topic/13/', $body);
		$this->assertStringNotContainsString('topic12', $body);
		$this->assertStringEndsWith('<!-- end -->', $body);
		$this->assertSame(array('11:start:1:0', '11:title_status:1:1', '11:title:1:1', '11:nav:1:1', '11:subject:1:1', '11:status:1:1', '11:row:1:1',
			'13:start:2:1', '13:title_status:3:3', '13:title:3:3', '13:nav:3:3', '13:subject:3:3', '13:status:3:3', '13:row:3:3'), $stages);
	}

	public function testTheStepsSeeTheForumThenThePage(): void {
		$steps = array();
		$this->kit->visitor->administrator = true;
		$this->kit->events->observe(ForumViewStep::class, function (ForumViewStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.$event->forum()->id().' '.(int) $event->moderating().(int) $event->mayPost().' '.$event->page().'/'.$event->pageCount().' after '.count($this->forums->log);
		});

		$this->page(array('id' => '4', 'p' => '9'));

		$this->assertSame(array('selected 4 00 0/0 after 1', 'paginated 4 11 1/2 after 1'), $steps);
	}
}
