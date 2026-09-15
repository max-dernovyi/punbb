<?php
/**
 * search.php as a module, built with no forum: who may search, the simple and
 * the advanced form, a keyword and author search run and stored, a stored
 * search's results, each quick search and who may ask for it, the results as
 * posts, topics and forums, and what observers change on the way.
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
use PunBB\Module\Search\Api\Data\SearchMarkInterface;
use PunBB\Module\Search\Api\Data\StoredSearchInterface;
use PunBB\Module\Search\Api\ResultsInterface;
use PunBB\Module\Search\Api\SearchesInterface;
use PunBB\Module\Search\Controller\SearchController;
use PunBB\Module\Search\Event\ForumChecklistRendering;
use PunBB\Module\Search\Event\ForumResultAssembling;
use PunBB\Module\Search\Event\NoResultsShowing;
use PunBB\Module\Search\Event\PostResultAssembling;
use PunBB\Module\Search\Event\QuickSearchSelected;
use PunBB\Module\Search\Event\ResultRowStarting;
use PunBB\Module\Search\Event\ResultsHeadAssembling;
use PunBB\Module\Search\Event\ResultsRendering;
use PunBB\Module\Search\Event\SearchActionValidating;
use PunBB\Module\Search\Event\SearchCaching;
use PunBB\Module\Search\Event\SearchFormRendering;
use PunBB\Module\Search\Event\SearchRequested;
use PunBB\Module\Search\Event\SearchStep;
use PunBB\Module\Search\Event\TopicResultAssembling;
use PunBB\Module\Search\Event\TopicResultsHeadAssembling;
use PunBB\Module\Search\Model\ResultForum;
use PunBB\Module\Search\Model\ResultPost;
use PunBB\Module\Search\Model\ResultTopic;
use PunBB\Module\Search\Model\SearchableForum;
use PunBB\Module\Search\Model\StoredSearch;
use PunBB\Module\Search\Words\SearchWordsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\TrackedTopics;

require_once __DIR__.'/PageFakes.php';

final class FakeSearchServices implements SearchesInterface, ResultsInterface, SearchWordsInterface {
	/** @var list<string> */
	public array $log = array();

	/** @var array<string, list<int>> LIKE pattern => the posts it matches */
	public array $words = array();

	/** @var array<string, list<int>> LIKE pattern => the members it matches */
	public array $authors = array();

	/** @var array<int, list<int>> member => their posts */
	public array $posts = array();

	/** @var array<int, StoredSearch> */
	public array $stored = array();

	/** @var list<ResultPost> */
	public array $postResults = array();

	/** @var list<ResultTopic> */
	public array $topicResults = array();

	/** @var list<ResultForum> */
	public array $forumResults = array();

	/** @var list<SearchableForum> */
	public array $searchable = array();

	public function markSearched(SearchMarkInterface ...$marks): void {
		foreach ($marks as $mark)
			$this->log[] = 'searched '.var_export($mark->memberId(), true).' '.$mark->address();
	}

	public function keywordMatches(string $pattern, int $searchIn): array {
		$this->log[] = 'word '.$pattern.' '.$searchIn;

		return $this->words[$pattern] ?? array();
	}

	public function authorIds(string $pattern): array {
		$this->log[] = 'author '.$pattern;

		return $this->authors[$pattern] ?? array();
	}

	public function authorPosts(array $userIds): array {
		$this->log[] = 'posts of '.implode(',', $userIds);

		return array_merge(...array_map(fn (int $id): array => $this->posts[$id] ?? array(), $userIds));
	}

	public function readableHits(array $postIds, int $groupId, ?array $forumIds, bool $asPosts): array {
		$this->log[] = 'hits '.implode(',', $postIds).' '.$groupId.' '.($forumIds === null ? 'all' : implode(',', $forumIds)).' '.($asPosts ? 'posts' : 'topics');

		return $postIds;
	}

	public function onlineIdents(): array {
		return array('member', '192.0.2.7');
	}

	public function pruneCache(string ...$keptIdents): void {
		$this->log[] = 'prune '.implode(',', $keptIdents);
	}

	public function store(StoredSearchInterface ...$searches): void {
		foreach ($searches as $search)
			$this->log[] = 'store '.$search->ident().' '.implode(',', $search->resultIds()).' '.var_export($search->sortBy(), true).' '.$search->sortDir().' '.$search->showAs();
	}

	public function stored(int $id, string $ident): ?StoredSearchInterface {
		$this->log[] = 'stored '.$id.' '.$ident;

		return $this->stored[$id] ?? null;
	}

	public function posts(array $postIds, ?int $sortBy, string $sortDir): array {
		$this->log[] = 'posts '.implode(',', $postIds).' '.var_export($sortBy, true).' '.$sortDir;

		return $this->postResults;
	}

	public function topics(array $topicIds, ?int $sortBy, string $sortDir, ?int $posterId): array {
		$this->log[] = 'topics '.implode(',', $topicIds).' '.var_export($sortBy, true).' '.$sortDir.' '.var_export($posterId, true);

		return $this->topicResults;
	}

	public function newTopics(int $groupId, int $since, ?int $forumId, ?int $posterId): array {
		$this->log[] = 'new '.$groupId.' '.$since.' '.var_export($forumId, true).' '.var_export($posterId, true);

		return $this->topicResults;
	}

	public function recentTopics(int $groupId, int $since, ?int $posterId): array {
		// The controller took the time a moment ago
		$this->log[] = 'recent '.$groupId.' '.(int) (round((time() - $since) / 10) * 10).' '.var_export($posterId, true);

		return $this->topicResults;
	}

	public function userPosts(int $groupId, int $userId): array {
		$this->log[] = 'user posts '.$groupId.' '.$userId;

		return $this->postResults;
	}

	public function userTopics(int $groupId, int $userId, ?int $posterId): array {
		$this->log[] = 'user topics '.$groupId.' '.$userId;

		return $this->topicResults;
	}

	public function subscribedTopics(int $groupId, int $userId, ?int $posterId): array {
		$this->log[] = 'subscriptions '.$groupId.' '.$userId;

		return $this->topicResults;
	}

	public function subscribedForums(int $groupId, int $userId): array {
		$this->log[] = 'forum subscriptions '.$groupId.' '.$userId;

		return $this->forumResults;
	}

	public function unansweredTopics(int $groupId, ?int $posterId): array {
		$this->log[] = 'unanswered '.$groupId;

		return $this->topicResults;
	}

	public function forums(int $groupId): array {
		$this->log[] = 'forums '.$groupId;

		return $this->searchable;
	}

	public function minimumLength(): int {
		return 3;
	}

	public function searchable(string $word): bool {
		return strlen($word) >= 2 && $word !== 'the';
	}
}

class SearchControllerTest extends TestCase {
	private PageKit $kit;

	private FakeSearchServices $search;

	protected function setUp(): void {
		$this->kit = new PageKit(array(SearchRequested::class, SearchActionValidating::class, QuickSearchSelected::class, SearchCaching::class, SearchStep::class, NoResultsShowing::class,
			ResultsHeadAssembling::class, ResultsRendering::class, TopicResultsHeadAssembling::class, ResultRowStarting::class, PostResultAssembling::class, TopicResultAssembling::class,
			ForumResultAssembling::class, SearchFormRendering::class, ForumChecklistRendering::class, MessageShowing::class, MessageRendering::class));
		$this->kit->language->real = array('search', 'common', 'topic', 'forum', 'index');
		$this->kit->settings->values += array('o_search_all_forums' => '1', 'o_show_dot' => '0', 'o_censoring' => '1');
		$this->kit->visitor->permissions[] = GroupPermission::Search;
		$this->kit->visitor->topicsPerPage = 2;
		$this->kit->visitor->postsPerPage = 2;

		$this->search = new FakeSearchServices();
		$this->search->topicResults = array(
			new ResultTopic(11, 'poster<1>', 'Darn sticky', 20, 100, 900, 25, 'last<1>', 1, true, true, 4, 'Forum <4>', true),
			new ResultTopic(12, 'poster2', 'Plain', 30, 200, 300, 31, 'last2', 4, false, false, 5, 'Five', false),
			new ResultTopic(13, 'poster3', 'Page two', 40, 300, 400, 41, 'last3', 0, false, false, 5, 'Five', false),
		);
		$this->search->postResults = array(
			new ResultPost(20, 'anna<a>', 7, 100, 'first darn words', true, 11, 'anna', 'Darn topic', 20, 90, 900, 25, 'bob', 3, 4, 'Forum <4>'),
			new ResultPost(25, 'bob', 1, 150, 'a reply', false, 11, 'anna', 'Darn topic', 20, 90, 900, 25, 'bob', 3, 4, 'Forum <4>'),
		);
	}

	/** @param array<string, mixed> $query */
	private function page(array $query): string {
		$controller = new SearchController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->search, $this->search, $this->search,
			$this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter, $this->kit->tokens);

		$response = $controller->handle(new Request('GET', '/', 'search.php', $query));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	public function testAVisitorWhoMayNotReadOrSearchGetsAMessage(): void {
		$this->kit->visitor->permissions = array(GroupPermission::Search);
		$this->assertStringContainsString('<p>You do not have permission to view these forums.</p>', $this->page(array()));

		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard);
		$this->assertStringContainsString('<p>You do not have permission to use the search feature.</p>', $this->page(array()));
		$this->assertSame(array(), $this->search->log);
	}

	public function testTheSimpleFormAsksForKeywordsAndLinksTheAdvancedOne(): void {
		$page = $this->page(array());

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('search', array('Board & Co', 'Search')), array($head->id, array_map(static fn ($crumb): string => $crumb->text, $head->crumbs)));
		$this->assertStringStartsWith('200  [search]<div class="main-head">'."\n\n\t\t".'<p class="options"><span class="first-item"><a href="/search_advanced?a=1&amp;b=2">Advanced search</a></span></p>'."\t\t".'<h2 class="hn"><span>Search forums using your criteria</span></h2>', $page);
		$this->assertStringContainsString('<input type="text" id="fld1" name="keywords" size="40" maxlength="100" required /></span>', $page);
		$this->assertStringNotContainsString('name="author"', $page);
		$this->assertStringNotContainsString('<fieldset class="mf-set', $page, 'every forum is searched: no forums to choose');
		$this->assertStringEndsWith("\t\t\t\t".'<span class="submit primary"><input type="submit" name="search" value="Search" /></span>'."\n\t\t\t</div>\n\t\t</form>\n\t</div>", $page);
		$this->assertSame(array(), $this->search->log);
	}

	public function testTheAdvancedFormNumbersItsFieldsAndListsTheForumsByCategory(): void {
		$this->search->searchable = array(new SearchableForum(1, 'Cat <1>', 4, 'Forum <4>'), new SearchableForum(1, 'Cat <1>', 5, 'Five'), new SearchableForum(2, 'Two', 6, 'Six'));
		$this->kit->events->observe(ForumChecklistRendering::class, static function (ForumChecklistRendering $event): void {
			if ($event->position() === ForumChecklistRendering::END && $event->forum()->id() === 4)
				$event->append('<!-- after 4 -->');
		});

		$page = $this->page(array('advanced' => '1'));

		$this->assertStringNotContainsString('Advanced search', $page);
		$this->assertStringContainsString('<ul class="info-list">'."\n\t\t\t\t".'<li><span>You may search for a single keyword', $page);
		$this->assertStringContainsString('<li><span>By default all forums are searched.', $page);
		$this->assertStringContainsString('name="keywords" size="40" maxlength="100"  /></span>', $page);
		$this->assertStringContainsString('<div class="sf-set set2">'."\n\t\t\t\t\t".'<div class="sf-box text">'."\n\t\t\t\t\t\t".'<label for="fld2"><span>Author\'s username</span>', $page);
		$this->assertStringContainsString('<select id="fld3" name="search_in">', $page);
		$this->assertStringContainsString('<fieldset class="mf-set set4">'."\n\t\t\t\t\t".'<legend><span>Select forums to search <em>If no forums are selected then all forums will be searched.</em></span></legend>', $page);
		$this->assertStringContainsString("\t\t\t\t\t\t<div class=\"checklist\">\n".
			"\t\t\t\t\t\t\t<fieldset>\n\t\t\t\t\t\t\t\t<legend><span>Cat &lt;1&gt;:</span></legend>\n".
			"\t\t\t\t\t\t\t\t<div class=\"checklist-item\"><span class=\"fld-input\"><input type=\"checkbox\" id=\"fld4\" name=\"forum[]\" value=\"4\" /></span> <label for=\"fld4\">Forum &lt;4&gt;</label></div>\n".
			"<!-- after 4 -->\t\t\t\t\t\t\t\t<div class=\"checklist-item\"><span class=\"fld-input\"><input type=\"checkbox\" id=\"fld5\" name=\"forum[]\" value=\"5\" /></span> <label for=\"fld5\">Five</label></div>\n".
			"\t\t\t\t\t\t\t</fieldset>\n\t\t\t\t\t\t\t<fieldset>\n\t\t\t\t\t\t\t\t<legend><span>Two:</span></legend>\n".
			"\t\t\t\t\t\t\t\t<div class=\"checklist-item\"><span class=\"fld-input\"><input type=\"checkbox\" id=\"fld6\" name=\"forum[]\" value=\"6\" /></span> <label for=\"fld6\">Six</label></div>\n".
			"\t\t\t\t\t\t\t</fieldset>\n\t\t\t\t\t\t</div>", $page);
		$this->assertStringContainsString('<fieldset class="frm-group group2">', $page);
		$this->assertStringContainsString('<div class="sf-set set1">'."\n\t\t\t\t\t".'<div class="sf-box select">'."\n\t\t\t\t\t\t".'<label for="fld7"><span>Sort results by</span>', $page, 'the results fieldset numbers its items from 1 again');
		$this->assertStringContainsString('<select id="fld7" name="sort_by">'."\n\t\t\t\t\t\t".'<option value="0">Post time</option>'."\n\t\t\t\t\t\t".'<option value="1">Author</option>', $page);
		$this->assertStringContainsString('id="fld8" name="sort_dir" value="ASC"', $page);
		$this->assertStringContainsString('id="fld11" name="show_as" value="posts" checked="checked"', $page);
		$this->assertSame(array('forums 3'), $this->search->log);
	}

	public function testAMemberWhoMustChooseForumsIsOfferedThemOnTheSimpleFormToo(): void {
		$this->kit->settings->values['o_search_all_forums'] = '0';

		$page = $this->page(array());

		$this->assertStringContainsString('<fieldset class="mf-set set2">', $page);
		$this->assertStringContainsString('<em>You must select at least one forum to search.</em>', $page);

		$this->kit->visitor->moderating = true;
		$this->assertStringNotContainsString('<fieldset class="mf-set', $this->page(array()));
	}

	public function testObserversAddFieldsAndChangeTheFormBeforeItIsShown(): void {
		$this->kit->events->observe(SearchFormRendering::class, static function (SearchFormRendering $event): void {
			if ($event->position() === SearchFormRendering::MAIN_OUTPUT_START)
			{
				$event->set(SearchFormRendering::HEAD_OPTIONS, 'probe', '<span>probe</span>');
				$event->set(SearchFormRendering::SORT, 'probe', '<option value="9">Probe</option>');
			}

			if ($event->position() === SearchFormRendering::PRE_KEYWORDS)
			{
				$event->append('<input id="fld'.($event->fieldCount() + 1).'" />');
				$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
			}
		});

		$page = $this->page(array('advanced' => '1'));

		$this->assertStringContainsString('<p class="options"><span>probe</span></p>', $page);
		$this->assertStringContainsString('<input id="fld1" />'."\t\t\t\t".'<div class="sf-set set2">', $page);
		$this->assertStringContainsString('<label for="fld2"><span>Keyword or words</span>', $page);
		$this->assertStringContainsString('<option value="3">Forum</option>'."\n\t\t\t\t\t\t".'<option value="9">Probe</option>', $page);
	}

	public function testAStoredSearchIdMustBeAPositiveNumberAndOneNotStoredFindsNothing(): void {
		foreach (array(array('search_id' => '0'), array('search_id' => 'x'), array('search_id' => array('1'))) as $query)
			$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page($query));

		$this->kit->events->dispatched = array();
		$page = $this->page(array('search_id' => '7'));

		$this->assertStringContainsString('<h2 class="hn"><span>Search results</span></h2>', $page);
		$this->assertStringContainsString('<p>Your search returned no hits. <span><a href="/search?a=1&amp;b=2">Perform new search</a></span></p>', $page);
		$this->assertSame(array('stored 7 member'), $this->search->log);
		$this->assertSame(array('SearchRequested', 'SearchStep', 'NoResultsShowing', 'MessageShowing', 'MessageRendering:start', 'MessageRendering:end'), $this->kit->events->dispatched);
	}

	public function testAStoredSearchListsItsPostsSortedAsItAsked(): void {
		$this->search->stored[7] = new StoredSearch(7, 'member', array(20, 25), 1, 'ASC', 'posts');

		$page = $this->page(array('search_id' => '7'));

		$this->assertSame(array('stored 7 member', 'posts 20,25 1 ASC'), $this->search->log);
		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('searchposts', array('Board & Co', 'Search results'), 1, 'Search options'), array($head->id, array_map(static fn ($crumb): string => $crumb->text, $head->crumbs), $head->page, $head->mainTitle?->html));
		$this->assertNull($head->pageCount);
		$this->assertSame('<p class="paging"><span class="pages">Pages</span> [pages 1 of 1 at search_results]</p>', $head->pagePost['paging']->html);

		$this->assertStringContainsString('[searchposts]<div class="main-head">'."\n\n\t\t".'<p class="options"><span class="first-item"><a href="/search?a=1&amp;b=2">Perform new search</a></span></p>'."\t\t".'<h2 class="hn"><span>Posts found: 1-2/2 in 1</span></h2>', $page);
		$this->assertStringContainsString('<div class="post odd firstpost topicpost resultpost">', $page);
		$this->assertStringContainsString('<h3 class="hn post-ident"><span class="post-num">1</span> <span class="post-byline"><span>Topic by </span><strong>anna&lt;a&gt;</strong></span> <span class="post-link"><a class="permalink" rel="bookmark" title="Permanent link to this post" href="/post/20?a=1&amp;b=2"><time>100</time></a></span></h3>', $page);
		$this->assertStringContainsString('<h4 class="hn post-title"><span><a class="permalink" rel="bookmark" title="Permanent link to this topic" href="/topic/11/slug-d-rn-topic?a=1&amp;b=2">Topic: d*rn topic</a> <small>(3 replies, posted in <a href="/forum/4/slug-forum-4-?a=1&amp;b=2">Forum &lt;4&gt;</a>)</small></span></h4>', $page);
		$this->assertStringContainsString("\t\t\t\t\t<p>first darn words (no smilies)</p>\t\t\t\t</div>\n\t\t\t</div>", $page);
		$this->assertStringContainsString('<p class="post-actions"><span><a href="/forum/4/slug-forum-4-?a=1&amp;b=2">Go to forum<span>: Forum &lt;4&gt;</span></a></span> <span><a class="permalink" rel="bookmark" title="Permanent link to this post" href="/post/20?a=1&amp;b=2">Go to post<span> 1</span></a></span></p>', $page);
		$this->assertStringContainsString('<div class="post even lastpost resultpost">', $page);
		$this->assertStringContainsString('<span>Reply by </span><strong>bob</strong>', $page);
		$this->assertStringContainsString('<span><a class="permalink" rel="bookmark" title="Permanent link to this topic" href="/topic/11/slug-d-rn-topic?a=1&amp;b=2">Go to topic<span>: d*rn topic</span></a></span>', $page);
		$this->assertStringEndsWith("\t</div>\n\n\t<div class=\"main-foot\">\n\t\t<h2 class=\"hn\"><span>Posts found: 1-2/2 in 1</span></h2>\n\t</div>", $page);
	}

	public function testAStoredTopicSearchListsAPageOfItsTopics(): void {
		$this->kit->settings->values['o_show_dot'] = '1';
		$this->kit->visitor->lastVisit = 250;
		$this->kit->visitor->tracked = new TrackedTopics(array(12 => 500), array(5 => 350));
		$this->search->stored[7] = new StoredSearch(7, 'member', array(11, 12, 13), null, 'DESC', 'topics');

		$page = $this->page(array('search_id' => '7'));

		$this->assertSame(array('stored 7 member', 'topics 11,12,13 NULL DESC 3'), $this->search->log);
		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('searchtopics', '(Page 1 of 2)'), array($head->id, $head->pageCount?->html));
		$this->assertSame(array('last' => '<link rel="last" href="/search_results/7~page=2" title="Page 2" />', 'next' => '<link rel="next" href="/search_results/7~page=2" title="Page 2" />'),
			array_map(static fn ($link): string => $link->html, $head->navigation));

		$this->assertStringContainsString('<p class="item-summary forum-noview"><span><strong class="subject-title">Topics</strong> found with details of <strong class="info-forum">Forum</strong>, <strong class="info-replies">replies</strong>, <strong class="info-lastpost">last post</strong>.</span></p>', $page);
		$this->assertStringContainsString('<div class="main-item odd main-first-item posted sticky closed new">', $page);
		$this->assertStringContainsString('<h3 class="hn"><span class="item-num">1</span> <span class="posted-mark">·</span> <span class="item-status"><em class="sticky">Sticky</em>, <em class="closed">Closed</em>:</span> <a href="/topic/11/slug-d-rn-sticky?a=1&amp;b=2">d*rn sticky</a></h3>', $page);
		$this->assertStringContainsString('<p><span class="item-starter">by <cite>poster&lt;1&gt;</cite></span> <span class="item-nav">( <em class="item-newposts"><a href="/topic_new_posts/11/slug-d-rn-sticky?a=1&amp;b=2" title="Go to the first new post since your last visit.">New posts</a></em> )</span></p>', $page);
		$this->assertStringContainsString('<li class="info-forum"><span class="label">Posted in </span><a href="/forum/4/slug-forum-4-?a=1&amp;b=2">Forum &lt;4&gt;</a></li>', $page, 'the forum name is escaped');
		$this->assertStringContainsString('<li class="info-replies"><strong>1</strong> <span class="label">Reply</span></li>', $page);
		$this->assertStringContainsString('<cite>by last&lt;1&gt;</cite></li>', $page);
		$this->assertStringContainsString('<div class="main-item even normal">', $page, 'topic 12 was read after its last post');
		$this->assertStringContainsString('<span class="item-nav">( <span>Pages&#160;</span>[pages -1 of 3 at topic by &#160;] )</span>', $page);
		$this->assertStringNotContainsString('Page two', $page);
	}

	public function testAQuickSearchNeedsItsMemberAndOnlyAnAdministratorLooksAtAnothersSubscriptions(): void {
		foreach (array('show_user_posts', 'show_user_topics', 'show_subscriptions', 'show_forum_subscriptions') as $action)
			foreach (array(array(), array('user_id' => '1'), array('user_id' => array('3'))) as $query)
				$this->assertStringContainsString('<p>Bad request.', $this->page(array('action' => $action) + $query), $action);

		$this->assertStringContainsString('<p>Bad request.', $this->page(array('action' => 'show_subscriptions', 'user_id' => '4')));
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('action' => 'nonsense')));
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('action' => array('show_new'))));
		$this->assertSame(array(), $this->search->log);

		$this->kit->visitor->administrator = true;
		$this->page(array('action' => 'show_subscriptions', 'user_id' => '4'));
		$this->kit->visitor->administrator = false;
		$this->page(array('action' => 'show_subscriptions', 'user_id' => '3'));
		$this->assertSame(array('subscriptions 3 4', 'subscriptions 3 3'), $this->search->log);

		$this->kit->visitor->guest = true;
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('action' => 'show_forum_subscriptions', 'user_id' => '3')));
		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('action' => 'show_new')));
	}

	public function testEachQuickSearchAsksForWhatItLists(): void {
		$this->search->topicResults = array();
		$this->search->postResults = array();

		$this->page(array('action' => 'show_new'));
		$this->page(array('action' => 'show_new', 'forum' => '5'));
		$this->page(array('action' => 'show_recent'));
		$this->page(array('action' => 'show_recent', 'value' => '60'));
		$this->page(array('action' => 'show_unanswered'));
		$this->page(array('action' => 'show_user_posts', 'user_id' => '9'));
		$page = $this->page(array('action' => 'show_user_topics', 'user_id' => '9'));

		$this->assertSame(array('new 3 1000 NULL NULL', 'new 3 1000 5 NULL', 'recent 3 86400 NULL', 'recent 3 60 NULL', 'unanswered 3', 'user posts 3 9', 'user topics 3 9'), $this->search->log);
		$this->assertStringContainsString('<h2 class="hn"><span>Topics by this user</span></h2>', $page);
		$this->assertStringContainsString('<p>There are no topics by this user in this forum. <span><a href="/search?a=1&amp;b=2">Perform new search</a></span></p>', $page);
	}

	public function testARecentTopicsAgeOutsideTheEpochIsKeptInsideIt(): void {
		$this->search->topicResults = array();

		$this->page(array('action' => 'show_recent', 'value' => '-9223372036854775807'));
		$this->page(array('action' => 'show_recent', 'value' => '9223372036854775807'));

		$this->assertSame('recent 3 0 NULL', $this->search->log[0]);
		$this->assertStringStartsWith('recent 3 ', $this->search->log[1]);
	}

	public function testNewTopicsOfAForumLinkAllOfItsTopicsAndMarkingTheBoardRead(): void {
		$this->page(array('action' => 'show_new', 'forum' => '5'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame('Topics with new posts', $head->crumbs[1]->text);
		$page = $this->page(array('action' => 'show_new', 'forum' => '5'));
		$this->assertStringContainsString('<p class="options"><span class="first-item"><a href="/search?a=1&amp;b=2">User defined search</a></span> <span><a href="/forum/4?a=1&amp;b=2">Show all topics</a></span></p>', $page);
		$this->assertStringContainsString('<p class="options"><span class="first-item"><a href="/mark_read/token-for-'.md5('markread3').'?a=1&amp;b=2">Mark all topics as read</a></span></p>', $page);

		$this->assertStringNotContainsString('Show all topics', $this->page(array('action' => 'show_new')));
	}

	public function testAMembersPostsAndTopicsNameThemAndLinkEachOther(): void {
		$this->page(array('action' => 'show_user_posts', 'user_id' => '7'));
		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('Posts by anna<a>', 'searchposts'), array($head->crumbs[1]->text, $head->id));
		$page = $this->page(array('action' => 'show_user_posts', 'user_id' => '7'));
		$this->assertStringContainsString('<span class="first-item"><a href="/search_user_topics/7?a=1&amp;b=2">Topics by anna&lt;a&gt;</a></span> <span><a href="/search?a=1&amp;b=2">User defined search</a></span>', $page);

		$page = $this->page(array('action' => 'show_user_topics', 'user_id' => '7'));
		$this->assertSame('Topics by poster<1>', $this->kit->chromes->opened[2]->crumbs[1]->text);
		$this->assertStringContainsString('<a href="/search_user_posts/7?a=1&amp;b=2">Posts by poster&lt;1&gt;</a>', $page);
	}

	public function testForumSubscriptionsListEachForumUnderItsCategory(): void {
		$this->search->forumResults = array(
			new ResultForum(1, 'Cat <1>', 4, 'Forum <4>', 'About <b>it</b>', '', 1, 2, 900, 25, 'last<1>'),
			new ResultForum(1, 'Cat <1>', 5, 'Away', '', 'http://example.com/?a=1&b=2', 0, 0, null, null, null),
			new ResultForum(2, 'Two', 6, 'Never', '', '', 3, 1, null, null, null),
		);

		$page = $this->page(array('action' => 'show_forum_subscriptions', 'user_id' => '3'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('searchforums', 'Forum subscriptions', 1, array(), array()), array($head->id, $head->crumbs[1]->text, $head->page, $head->pagePost, $head->navigation));
		$this->assertStringStartsWith('200  [searchforums]<div class="main-head">'."\n\t\t\t\t\t".'<h2 class="hn"><span>Cat &lt;1&gt;</span></h2>', $page);
		$this->assertStringContainsString('<p class="item-summary"><span><strong class="subject-title">Forums</strong> in this category with details of <strong class="info-topics">topics</strong>, <strong class="info-posts">posts</strong>, <strong class="info-lastpost">last post</strong></span></p>'."\n\t\t\t\t</div>\n\t\t\t\t".'<div id="category1" class="main-content main-category">', $page);
		$this->assertStringContainsString('<div id="forum4" class="main-item odd main-first-item">'."\n\t\t\t\t".'<span class="icon "><!-- --></span>', $page);
		$this->assertStringContainsString('<h3 class="hn"><a href="/forum/4/slug-forum-4-?a=1&amp;b=2"><span>Forum &lt;4&gt;</span></a></h3>'."\n\t\t\t\t".'<p>About <b>it</b></p>', $page);
		$this->assertStringContainsString('<li class="info-topics"><strong>1</strong> <span class="label">topic</span></li>'."\n\t\t\t\t".'<li class="info-posts"><strong>2</strong> <span class="label">posts</span></li>', $page);
		$this->assertStringContainsString('<div id="forum5" class="main-item even redirect">', $page);
		$this->assertStringContainsString('<h3 class="hn"><a class="external" href="http://example.com/?a=1&amp;b=2" title="Link to http://example.com/?a=1&amp;b=2"><span>Away</span></a></h3>'."\n\t\t\t\t".'<p><span>(This forum is located on an external site)</span></p>', $page);
		$this->assertStringContainsString("\t</div>\n\t\t\t\t<div class=\"main-head\">\n\t\t\t\t\t<h2 class=\"hn\"><span>Two</span></h2>", $page);
		$this->assertStringContainsString('<div id="category2" class="main-content main-category">', $page);
		$this->assertStringContainsString('<div id="forum6" class="main-item odd main-first-item">', $page);
		$this->assertStringContainsString('<li class="info-lastpost"><strong>Never</strong></li>', $page);
		$this->assertStringContainsString('<h2 class="hn"><span>Forums found: 1-3/3 in 1</span></h2>', $page);
	}

	public function testObserversChangeTheHeadTheRowsAndTheirCount(): void {
		$this->kit->events->observe(ResultsHeadAssembling::class, static function (ResultsHeadAssembling $event): void {
			$event->set(ResultsHeadAssembling::HEAD_OPTIONS, 'probe', '<span>probe</span>');
		});
		$this->kit->events->observe(ResultsRendering::class, static function (ResultsRendering $event): void {
			if ($event->position() === ResultsRendering::START)
				$event->remove(ResultsRendering::FOOT_OPTIONS, 'mark_all');
			$event->append('<!-- '.$event->position().' -->');
		});
		$this->kit->events->observe(ResultRowStarting::class, static function (ResultRowStarting $event): void {
			$event->append('<!-- row -->');
			if ($event->result() instanceof ResultTopic && $event->result()->id() === 12)
				$event->count($event->itemCount() + 1);
		});
		$this->kit->events->observe(TopicResultAssembling::class, static function (TopicResultAssembling $event): void {
			if ($event->stage() === TopicResultAssembling::TITLE)
				$event->set(TopicResultAssembling::PART_TITLE, 'probe', '<em>probe #'.$event->number().'</em>');
		});

		$page = $this->page(array('action' => 'show_new'));

		$this->assertStringStartsWith('200  [searchtopics]<!-- start -->'."\n\t".'<div class="main-head">'."\n\n\t\t".'<p class="options"><span>probe</span> <span><a href="/search?a=1&amp;b=2">User defined search</a></span></p>', $page, 'an option added first takes the first place');
		$this->assertStringNotContainsString('Mark all topics as read', $page);
		$this->assertStringContainsString('<!-- row -->'."\t\t".'<div class="main-item odd main-first-item', $page);
		$this->assertStringContainsString('<a href="/topic/11/slug-d-rn-sticky?a=1&amp;b=2">d*rn sticky</a> <em>probe #1</em></h3>', $page);
		$this->assertStringContainsString('<div class="main-item odd normal">', $page, 'the second topic counts as the third');
		$this->assertStringContainsString('<span class="item-num">3</span> <a href="/topic/12/slug-plain?a=1&amp;b=2">Plain</a> <em>probe #3</em>', $page);
		$this->assertStringEndsWith("\t</div>\n<!-- end -->", $page);
	}

	public function testAKeywordSearchNeedsSomethingToLookFor(): void {
		foreach (array(array(), array('keywords' => '  '), array('keywords' => '*%'), array('author' => '**'), array('keywords' => array('x'))) as $query)
			$this->assertStringContainsString('<p>You have to enter at least one keyword and/or an author to search for.</p>', $this->page(array('action' => 'search') + $query));

		$this->assertSame(array(), $this->search->log);

		$this->assertStringContainsString('<p>You have to enter at least one keyword and/or an author to search for.</p>', $this->page(array('action' => 'search', 'keywords' => 'ab*', 'author' => 'a%')));
		$this->assertSame(array(), $this->search->log, 'too short, wildcards aside');
	}

	public function testAKeywordSearchWaitsOutTheFloodInterval(): void {
		$this->kit->visitor->lastSearchAt = time() - 5;

		$this->assertStringContainsString('<p>At least 30 seconds have to pass between searches.', $this->page(array('action' => 'search', 'keywords' => 'words')));
		$this->assertSame(array(), $this->search->log);
	}

	public function testKeywordsNarrowWidenAndExcludeAndTheSearchIsStoredForItsResults(): void {
		$this->search->words = array('cat%' => array(1, 2, 3), 'dog' => array(2, 3, 4), 'bird' => array(5), 'fish' => array(3));

		$response = $this->page(array('action' => 'search', 'keywords' => ' Cat* the, "dog" or bird not fish cat* ', 'search_in' => 'topic', 'sort_by' => '2', 'sort_dir' => 'ASC', 'show_as' => 'topics'));

		$this->assertMatchesRegularExpression('#^302 /search_results/[0-9]+\?a=1&b=2 $#', $response);
		$this->assertSame(array(
			'searched 3 192.0.2.7',
			'word cat% -1', 'word dog -1', 'word bird -1', 'word fish -1',
			'hits 2,5 3 all topics',
			'prune member,192.0.2.7',
			'store member 2,5 2 ASC topics',
		), $this->search->log);
	}

	public function testAnAuthorSearchIntersectsWithTheKeywordsAndKeepsToTheForumsChosen(): void {
		$this->search->words = array('words' => array(1, 2, 3));
		$this->search->authors = array('ann%' => array(7, 8));
		$this->search->posts = array(7 => array(2), 8 => array(3, 9));
		$this->kit->visitor->guest = true;

		$this->page(array('action' => 'search', 'keywords' => 'Words', 'author' => 'Ann*', 'forum' => array('4', 'x'), 'search_in' => 'message'));

		$this->assertSame(array('searched NULL 192.0.2.7', 'word words 1', 'author ann%', 'posts of 7,8', 'hits 2,3 3 4,0 posts', 'prune member,192.0.2.7', 'store 192.0.2.7 2,3 NULL DESC posts'), $this->search->log);
	}

	public function testTheGuestAccountIsNoAuthorAndAChoiceOfEveryForumStillNeedsAForumWhereTheBoardAsks(): void {
		$this->kit->settings->values['o_search_all_forums'] = '0';

		$this->assertStringContainsString('<p>Your search returned no hits.', $this->page(array('action' => 'search', 'author' => 'Guest')));
		$this->assertSame(array('searched 3 192.0.2.7'), $this->search->log);

		$this->search->log = array();
		$this->search->authors = array('anna' => array(7));
		$this->search->posts = array(7 => array(2));
		$this->page(array('action' => 'search', 'author' => 'anna'));
		$this->assertSame('hits 2 3 -1 posts', $this->search->log[3]);
	}

	public function testAKeywordSearchOfOnlyStopwordsFindsNothing(): void {
		$page = $this->page(array('action' => 'search', 'keywords' => 'the the'));

		$this->assertStringContainsString('<p>Your search returned no hits. <span><a href="/search?a=1&amp;b=2">Perform new search</a></span></p>', $page);
		$this->assertSame(array('searched 3 192.0.2.7'), $this->search->log);
	}

	public function testAnObserverStoppingTheSearchLeavesTheSearcherOnTheForm(): void {
		$this->kit->events->observe(SearchCaching::class, static function (SearchCaching $event): void {
			if ($event->step() === SearchCaching::START && $event->criteria()->keywords() === 'stop')
				$event->stop();
			if ($event->step() === SearchCaching::STORED)
				$event->stop();
		});
		$this->search->words = array('words' => array(1));

		$this->assertStringContainsString('[search]<div class="main-head">', $this->page(array('action' => 'search', 'keywords' => 'stop')));
		$this->assertSame(array(), $this->search->log);

		$this->assertStringContainsString('[search]<div class="main-head">', $this->page(array('action' => 'search', 'keywords' => 'words')));
		$this->assertSame('store member 1 NULL DESC posts', $this->search->log[4]);
	}

	public function testAnActionAnObserverAddsIsValidAndListsNothingOfItsOwn(): void {
		$this->kit->events->observe(SearchActionValidating::class, static function (SearchActionValidating $event): void {
			$event->setActions(array_merge($event->actions(), array('show_probe')));
		});
		$values = array();
		$this->kit->events->observe(QuickSearchSelected::class, static function (QuickSearchSelected $event) use (&$values): void {
			$values[] = $event->action().' '.var_export($event->value(), true);
			$event->setValue(42);
		});
		$this->kit->events->observe(NoResultsShowing::class, static function (NoResultsShowing $event): void {
			$event->setSearchAgain('<a href="/probe">Again</a>');
		});

		$page = $this->page(array('action' => 'show_probe'));

		$this->assertSame(array('show_probe NULL'), $values);
		$this->assertStringContainsString('<p>Your search returned no hits. <span><a href="/probe">Again</a></span></p>', $page);
		$this->assertSame(array(), $this->search->log);
	}
}
