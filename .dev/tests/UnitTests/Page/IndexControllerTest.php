<?php
/**
 * index.php as a module, built with no forum: who may see it, the categories
 * and forums as rows, which forums have posts the visitor has not read, the
 * rows' parts as observers change them at each stage, the statistics and the
 * visitors online.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Index\Api\BoardIndexInterface;
use PunBB\Module\Index\Api\Data\StatisticsInterface;
use PunBB\Module\Index\Controller\IndexController;
use PunBB\Module\Index\Event\CategoryHeadAssembling;
use PunBB\Module\Index\Event\ForumRowAssembling;
use PunBB\Module\Index\Event\IndexRendering;
use PunBB\Module\Index\Event\IndexRequested;
use PunBB\Module\Index\Event\OnlineInfoAssembling;
use PunBB\Module\Index\Event\OnlineVisitorListing;
use PunBB\Module\Index\Event\StatisticsAssembling;
use PunBB\Module\Index\Model\Forum;
use PunBB\Module\Index\Model\Moderator;
use PunBB\Module\Index\Model\OnlineVisitor;
use PunBB\Module\Index\Model\Statistics;
use PunBB\Module\Index\Model\TopicActivity;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\TrackedTopics;

require_once __DIR__.'/PageFakes.php';

final class FakeBoardIndex implements BoardIndexInterface {
	/** @var list<Forum> */
	public array $forums = array();

	/** @var list<TopicActivity> */
	public array $activeTopics = array();

	/** @var list<OnlineVisitor> */
	public array $online = array();

	public function __construct(private array &$log) {}

	public function forums(int $groupId): array {
		$this->log[] = 'forums '.$groupId;

		return $this->forums;
	}

	public function activeTopics(int $groupId, int $since): array {
		$this->log[] = 'active '.$groupId.' '.$since;

		return $this->activeTopics;
	}

	public function statistics(): StatisticsInterface {
		$this->log[] = 'statistics';

		return new Statistics(1500, 7, 'new <member>', 30, 1234);
	}

	public function onlineVisitors(): array {
		$this->log[] = 'online';

		return $this->online;
	}
}

class IndexControllerTest extends TestCase {
	private PageKit $kit;

	private FakeBoardIndex $board;

	/** @var list<string> */
	private array $log = array();

	protected function setUp(): void {
		$this->kit = new PageKit(array(IndexRequested::class, IndexRendering::class, CategoryHeadAssembling::class, ForumRowAssembling::class, StatisticsAssembling::class,
			OnlineVisitorListing::class, OnlineInfoAssembling::class, MessageShowing::class, MessageRendering::class));
		$this->kit->language->real = array('index', 'common');
		$this->kit->chromes->log = &$this->log;
		$this->board = new FakeBoardIndex($this->log);

		$this->board->forums = array(
			self::forum(1, 1, 'First <cat>', 'Plain', lastPost: 2000),
			self::forum(2, 1, 'First <cat>', 'Elsewhere', redirect: 'http://example.com/?a=1&b=2'),
			self::forum(3, 2, 'Second', 'Moderated', moderators: array(new Moderator(8, 'mod<8>'), new Moderator(9, 'nine'))),
		);
	}

	private static function forum(int $id, int $category, string $categoryName, string $name, ?int $lastPost = null, string $redirect = '', array $moderators = array()): Forum {
		return new Forum($category, $categoryName, $id, $name, $id === 1 ? 'The <em>first</em> forum' : '', $redirect, $moderators, $id, $id * 10 + 1, $lastPost, $lastPost !== null ? 50 : null, $lastPost !== null ? 'poster<'.$id.'>' : null);
	}

	private function index(): string {
		$controller = new IndexController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->board,
			$this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter);

		return $controller->handle(new Request('GET', '/', 'index.php'))->body;
	}

	public function testAVisitorWhoMayNotReadTheBoardGetsAMessage(): void {
		$this->kit->visitor->permissions = array();

		$this->assertStringContainsString('<p>You do not have permission to view these forums.</p>', $this->index());
		$this->assertSame(array('IndexRequested', 'MessageShowing', 'MessageRendering:start', 'MessageRendering:end'), $this->kit->events->dispatched);
	}

	public function testTheForumsAreListedAsRowsUnderTheirCategories(): void {
		$body = $this->index();

		$head = $this->kit->chromes->opened[0];
		$this->assertSame('index', $head->id);
		$this->assertTrue($head->indexable);
		$this->assertSame(array(), $head->crumbs);
		$this->assertSame('Board &amp; Co', $head->mainTitle?->html);

		$this->assertSame(array('active 3 1000', 'open', 'forums 3', 'statistics'), $this->log);
		$this->assertStringStartsWith("[index]<div class=\"main-head\">\n\t\t<h2 class=\"hn\"><span>First &lt;cat&gt;</span></h2>\n\t</div>\n\t<div class=\"main-subhead\">\n\t\t<p class=\"item-summary\"><span><strong class=\"subject-title\">Forums</strong> in this category with details of <strong class=\"info-topics\">topics</strong>, <strong class=\"info-posts\">posts</strong>, <strong class=\"info-lastpost\">last post</strong></span></p>\n\t</div>\n\t<div id=\"category1\" class=\"main-content main-category\">\n", $body);
		$this->assertStringContainsString("\t\t<div id=\"forum1\" class=\"main-item odd main-first-item\">\n\t\t\t<span class=\"icon \"><!-- --></span>\n\t\t\t<div class=\"item-subject\">\n\t\t\t\t<h3 class=\"hn\"><a href=\"/forum/1/slug-plain?a=1&amp;b=2\"><span>Plain</span></a></h3>\n\t\t\t\t<p>The <em>first</em> forum</p>\n\t\t\t</div>\n\t\t\t<ul class=\"item-info\">\n\t\t\t\t<li class=\"info-topics\"><strong>1</strong> <span class=\"label\">topic</span></li>\n\t\t\t\t<li class=\"info-posts\"><strong>11</strong> <span class=\"label\">posts</span></li>\n\t\t\t\t<li class=\"info-lastpost\"><span class=\"label\">Last post:</span> <strong><a href=\"/post/50?a=1&amp;b=2\"><time>2000</time></a></strong> <cite>by poster&lt;1&gt;</cite></li>\n\t\t\t</ul>\n\t\t</div>\n", $body);
		$this->assertStringContainsString("\t\t<div id=\"forum2\" class=\"main-item even redirect\">\n\t\t\t<span class=\"icon redirect\"><!-- --></span>\n\t\t\t<div class=\"item-subject\">\n\t\t\t\t<h3 class=\"hn\"><a class=\"external\" href=\"http://example.com/?a=1&amp;b=2\" title=\"Link to http://example.com/?a=1&amp;b=2\"><span>Elsewhere</span></a></h3>\n\t\t\t\t<p><span>(This forum is located on an external site)</span></p>\n\t\t\t</div>\n\t\t\t<ul class=\"item-info\">\n\t\t\t\t<li class=\"info-topics\"><span class=\"label\">No topic information</span></li>", $body);
		$this->assertStringContainsString("\t\t</div>\n\t</div>\n\t<div class=\"main-head\">\n\t\t<h2 class=\"hn\"><span>Second</span></h2>", $body);
		$this->assertStringContainsString("<div id=\"category2\" class=\"main-content main-category\">\n\t\t<div id=\"forum3\" class=\"main-item odd main-first-item\">", $body);
		$this->assertStringContainsString("<li class=\"info-lastpost\"><strong>Never</strong></li>\n\t\t\t</ul>\n\t\t</div>\n\t</div>[info]", $body);
	}

	public function testTheModeratorsAreNamedWhereTheBoardShowsThem(): void {
		$this->assertStringNotContainsString('modlist', $this->index());

		$this->kit->settings->values['o_show_moderators'] = '1';
		$this->assertStringContainsString('<p><span class="modlist">Moderated by <a href="/user/8?a=1&amp;b=2">mod&lt;8&gt;</a>, <a href="/user/9?a=1&amp;b=2">nine</a></span></p>', $this->index());

		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard);
		$this->assertStringContainsString('<p><span class="modlist">Moderated by mod&lt;8&gt;, nine</span></p>', $this->index());
	}

	public function testAForumWithAPostTheVisitorHasNotReadIsMarkedNew(): void {
		$this->board->activeTopics = array(new TopicActivity(1, 11, 2000));

		$new = '<span class="icon new"><!-- --></span>';
		$link = '<h3 class="hn"><a href="/forum/1/slug-plain?a=1&amp;b=2"><span>Plain</span></a> <small>( <a href="/search_new_results/1?a=1&amp;b=2" title="This forum contain posts made since your last visit.">New posts</a> )</small></h3>';

		$body = $this->index();
		$this->assertStringContainsString('<div id="forum1" class="main-item odd main-first-item new">', $body);
		$this->assertStringContainsString($new, $body);
		$this->assertStringContainsString($link, $body);

		$this->kit->visitor->tracked = new TrackedTopics(array(11 => 2000));
		$this->assertStringNotContainsString($new, $this->index(), 'the topic was read since');

		$this->kit->visitor->tracked = new TrackedTopics(array(), array(1 => 2000));
		$this->assertStringNotContainsString($new, $this->index(), 'the forum was marked read since');

		$this->kit->visitor->tracked = new TrackedTopics(array(11 => 1500), array(1 => 1800));
		$this->assertStringContainsString($new, $this->index(), 'read before the post');

		$this->kit->visitor->lastVisit = 2000;
		$this->assertStringNotContainsString($new, $this->index(), 'posted before the last visit');
	}

	public function testAGuestIsShownNothingNew(): void {
		$this->kit->visitor->guest = true;
		$this->board->activeTopics = array(new TopicActivity(1, 11, 2000));

		$this->assertStringNotContainsString('icon new', $this->index());
		$this->assertNotContains('active 3 1000', $this->log);
	}

	public function testAnEmptyBoardSaysSo(): void {
		$this->board->forums = array();

		$this->assertStringStartsWith("[index]<div class=\"main-head\">\n\t\t<h2 class=\"hn\"><span>Forum message</span></h2>\n\t</div>\n\t<div class=\"main-content main-message\">\n\t\t<p>Board is empty</p>\n\t</div>[info]", $this->index());
	}

	public function testObserversChangeTheRowAtEachStage(): void {
		$stages = array();
		$this->kit->events->observe(ForumRowAssembling::class, function (ForumRowAssembling $event) use (&$stages): void {
			$stages[] = $event->forum()->id().':'.$event->stage();

			if ($event->stage() === ForumRowAssembling::START)
				$event->append('<!--start '.$event->forum()->id().' '.$event->itemCount().'-->');

			if ($event->stage() === ForumRowAssembling::TITLE)
				$event->set(ForumRowAssembling::PART_TITLE, 'probe', '<em>probed</em>');

			if ($event->stage() === ForumRowAssembling::BODY)
				$event->remove(ForumRowAssembling::PART_BODY_INFO, 'lastpost');

			if ($event->stage() === ForumRowAssembling::ROW && $event->forum()->id() === 1)
			{
				$this->assertSame(' odd main-first-item', $event->style());
				$event->setStyle(' probed');
				$event->set(ForumRowAssembling::PART_STATUS, 'probe', 'probe');
				$event->append('<!--row-->');
			}
		});
		$this->kit->events->observe(CategoryHeadAssembling::class, function (CategoryHeadAssembling $event): void {
			$event->set(CategoryHeadAssembling::INFO, 'probe', '<strong>probe '.$event->number().'</strong>');
			$event->append('<!--category '.$event->firstForum()->categoryName().'-->');
		});
		$this->kit->settings->values['o_show_moderators'] = '1';

		$body = $this->index();

		$this->assertSame(array('1:start', '1:title', '1:subject', '1:body', '1:row', '2:start', '2:redirect_subject', '2:redirect_body', '2:row',
			'3:start', '3:title', '3:moderators', '3:subject', '3:body', '3:row'), $stages);
		$this->assertStringStartsWith('[index]<!--start 1 0--><!--category First <cat>-->	<div class="main-head">', $body);
		$this->assertStringContainsString('<strong class="info-lastpost">last post</strong>, <strong>probe 1</strong></span></p>', $body);
		$this->assertStringContainsString("main-category\">\n<!--row-->\t\t<div id=\"forum1\" class=\"main-item probed\">\n\t\t\t<span class=\"icon probe\"><!-- --></span>", $body);
		$this->assertStringContainsString('<span>Plain</span></a> <em>probed</em></h3>', $body);
		$this->assertStringNotContainsString('info-lastpost"><span class="label">Last post:', $body);
		$this->assertStringContainsString("\t\t</div>\n<!--start 2 1-->\t\t<div id=\"forum2\" class=\"main-item even redirect\">", $body);
		$this->assertStringContainsString("<!--start 3 2-->\t</div>\n<!--category Second-->\t<div class=\"main-head\">", $body);
	}

	public function testTheStatisticsAndTheVisitorsOnlineFollowTheForums(): void {
		$this->kit->settings->values['o_users_online'] = '1';
		$this->board->online = array(new OnlineVisitor(1, '192.0.2.1'), new OnlineVisitor(4, 'anna'), new OnlineVisitor(5, 'bob <b>'));
		$this->kit->events->observe(IndexRendering::class, fn (IndexRendering $event) => $event->append('<!--'.$event->position().'-->'));

		$body = $this->index();

		$info = substr($body, (int) strpos($body, '[info]') + 6);
		$this->assertSame('<!--info_output_start--><div id="brd-stats" class="gen-content">'."\n\t".'<h2 class="hn"><span>Forum statistics</span></h2>'."\n\t<ul>\n\t\t".
			'<li class="st-users"><span>Total number of registered users: <strong>1&#160;500</strong></span></li>'."\n\t\t".
			'<li class="st-users"><span>Newest registered user: <strong><a href="/user/7?a=1&amp;b=2">new &lt;member&gt;</a></strong></span></li>'."\n\t\t".
			'<li class="st-activity"><span>Total number of topics: <strong>30</strong></span></li>'."\n\t\t".
			'<li class="st-activity"><span>Total number of posts: <strong>1&#160;234</strong></span></li>'."\n\t</ul>\n</div>\n".
			'<!--stats_end--><!--users_online_start--><div id="brd-online" class="gen-content">'."\n\t".
			'<h3 class="hn"><span>Currently online: <strong>1</strong> guest, <strong>2</strong> registered users</span></h3>'."\n\t".
			'<p><a href="/user/4?a=1&amp;b=2">anna</a>, <a href="/user/5?a=1&amp;b=2">bob &lt;b&gt;</a></p>'."\n".
			"<!--new_online_data--></div>\n<!--users_online_end--><!--info_end-->", $info);
		$this->assertStringEndsWith("</div>\n<!--end-->[info]".$info, $body);
		$this->assertSame(array('active 3 1000', 'open', 'forums 3', 'statistics', 'online'), $this->log);
	}

	public function testObserversChangeTheStatisticsAndTheVisitorsOnline(): void {
		$this->kit->settings->values['o_users_online'] = '1';
		$this->board->online = array(new OnlineVisitor(4, 'anna'));
		$listed = array();

		$this->kit->events->observe(StatisticsAssembling::class, function (StatisticsAssembling $event): void {
			$event->remove('no_of_posts');
			$event->append('<!--newest '.$event->statistics()->newestUsername().'-->');
		});
		$this->kit->events->observe(OnlineVisitorListing::class, function (OnlineVisitorListing $event) use (&$listed): void {
			$listed[] = $event->visitor()->ident();
		});
		$this->kit->events->observe(OnlineInfoAssembling::class, function (OnlineInfoAssembling $event): void {
			$this->assertSame(array(0, 1), array($event->guestCount(), $event->memberCount()));
			$event->remove(OnlineInfoAssembling::COUNTS, 'guests');
			$event->set(OnlineInfoAssembling::MEMBERS, 'extra', 'extra');
		});

		$body = $this->index();

		$this->assertStringContainsString('[info]<!--newest new <member>--><div id="brd-stats"', $body);
		$this->assertStringNotContainsString('Total number of posts', $body);
		$this->assertSame(array('anna'), $listed);
		$this->assertStringContainsString('<h3 class="hn"><span>Currently online: <strong>1</strong> registered user</span></h3>'."\n\t".'<p><a href="/user/4?a=1&amp;b=2">anna</a>, extra</p>', $body);
	}

	public function testABoardThatDoesNotListTheVisitorsOnlineSkipsThem(): void {
		$positions = array();
		$this->kit->events->observe(IndexRendering::class, function (IndexRendering $event) use (&$positions): void {
			$positions[] = $event->position();
		});

		$body = $this->index();

		$this->assertStringNotContainsString('brd-online', $body);
		$this->assertNotContains('online', $this->log);
		$this->assertSame(array('main_output_start', 'end', 'info_output_start', 'stats_end', 'users_online_start', 'info_end'), $positions);
	}
}
