<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Controller;

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
use PunBB\Module\Search\Api\Data\ListingInterface;
use PunBB\Module\Search\Api\Data\ResultForumInterface;
use PunBB\Module\Search\Api\Data\ResultPostInterface;
use PunBB\Module\Search\Api\Data\ResultTopicInterface;
use PunBB\Module\Search\Api\ResultsInterface;
use PunBB\Module\Search\Api\SearchesInterface;
use PunBB\Module\Search\Event\ForumChecklistRendering;
use PunBB\Module\Search\Event\NoResultsShowing;
use PunBB\Module\Search\Event\QuickSearchSelected;
use PunBB\Module\Search\Event\ResultsHeadAssembling;
use PunBB\Module\Search\Event\ResultsRendering;
use PunBB\Module\Search\Event\SearchActionValidating;
use PunBB\Module\Search\Event\SearchCaching;
use PunBB\Module\Search\Event\SearchFormRendering;
use PunBB\Module\Search\Event\SearchRequested;
use PunBB\Module\Search\Event\SearchStep;
use PunBB\Module\Search\Model\Listing;
use PunBB\Module\Search\Model\SearchCriteria;
use PunBB\Module\Search\Model\SearchMark;
use PunBB\Module\Search\Model\StoredSearch;
use PunBB\Module\Search\View\Paging;
use PunBB\Module\Search\View\ResultRows;
use PunBB\Module\Search\View\SearchFormView;
use PunBB\Module\Search\Words\SearchWordsInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\TrackedTopics;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * search.php: the search form; a keyword or author search, stored for the
 * searcher and answered with its results page; a stored search's results;
 * and the quick searches — new and recent topics, a member's posts and
 * topics, subscriptions and unanswered topics.
 */
final class SearchController implements ControllerInterface {
	private const TEMPLATES = __DIR__.'/../templates/';

	/** The actions search.php knows before extension code adds any. */
	private const ACTIONS = array('search', 'show_new', 'show_recent', 'show_user_posts', 'show_user_topics', 'show_subscriptions', 'show_forum_subscriptions', 'show_unanswered');

	/** The quick searches that take a member. */
	private const MEMBER_SEARCHES = array('show_user_posts', 'show_user_topics', 'show_subscriptions', 'show_forum_subscriptions');

	/** A quick search's heading when it finds nothing, and the message saying so. */
	private const QUICK_SEARCHES = array(
		'show_new'					=> array('Topics with new', 'No new posts'),
		'show_recent'				=> array('Recently active topics', 'No recent posts'),
		'show_user_posts'			=> array('Posts by user', 'No user posts'),
		'show_user_topics'			=> array('Topics by user', 'No user topics'),
		'show_subscriptions'		=> array('Subscriptions', 'No subscriptions'),
		'show_forum_subscriptions'	=> array('Forum subscriptions', 'No forum subscriptions'),
		'show_unanswered'			=> array('Unanswered topics', 'No unanswered'),
	);

	/** How far back show_recent looks when the request does not say. */
	private const RECENT = 86400;

	/** The largest id a stored search is given. */
	private const MAX_SEARCH_ID = 2147483647;

	/** The fewest characters an author search must carry, wildcards aside. */
	private const MIN_AUTHOR = 2;

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly SearchesInterface $searches,
		private readonly ResultsInterface $results,
		private readonly SearchWordsInterface $words,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter,
		private readonly CsrfTokensInterface $tokens
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new SearchRequested());

		$strings = $this->language->strings('search');

		if (!$this->visitor->can(GroupPermission::ReadBoard))
			return $this->messages->respond($this->language->text('common', 'No view'), json: $request->xhr);

		if (!$this->visitor->can(GroupPermission::Search))
			return $this->messages->respond(self::string($strings, 'No search permission'), json: $request->xhr);

		$listing = null;
		$fetch = null;

		if (isset($request->query['search_id']))
		{
			$id = is_scalar($request->query['search_id']) ? intval($request->query['search_id']) : 0;
			if ($id < 1)
				return $this->badRequest($request);

			$stored = $this->searches->stored($id, $this->ident());
			$showAs = $stored?->showAs() === Listing::POSTS ? Listing::POSTS : Listing::TOPICS;
			$listing = new Listing(null, $showAs, 'search_results', $id);

			if ($stored !== null && $stored->resultIds() !== array())
				$fetch = $showAs === Listing::POSTS
					? fn (): array => $this->results->posts($stored->resultIds(), $stored->sortBy(), $stored->sortDir())
					: fn (): array => $this->results->topics($stored->resultIds(), $stored->sortBy(), $stored->sortDir(), $this->posterId());
		}
		else if (isset($request->query['action']))
		{
			$action = is_string($request->query['action']) ? $request->query['action'] : '';

			$validating = new SearchActionValidating($action, self::ACTIONS);
			$this->events->dispatch($validating);

			if (!($validating->decision() ?? in_array($action, $validating->actions(), true)))
				return $this->badRequest($request);

			if ($action === 'search')
				return $this->search($request, $strings);

			$value = null;
			if (in_array($action, self::MEMBER_SEARCHES, true))
			{
				$value = self::integer($request->query['user_id'] ?? 0);
				if ($value < 2)
					return $this->badRequest($request);
			}
			else if ($action === 'show_recent')
				$value = isset($request->query['value']) ? self::integer($request->query['value']) : self::RECENT;
			else if ($action === 'show_new')
				$value = isset($request->query['forum']) ? self::integer($request->query['forum']) : -1;

			$selected = new QuickSearchSelected($action, $value);
			$this->events->dispatch($selected);
			$value = $selected->value();

			$quick = $this->quickSearch($request, $action, $value);
			if ($quick instanceof Response)
				return $quick;

			[$listing, $fetch] = $quick;
		}

		$this->events->dispatch(new SearchStep(SearchStep::QUERYING, $listing));

		if ($listing === null)
			return $this->form($request, $strings);

		if ($fetch === null)
			return $this->noResults($request, null, $strings);

		$this->events->dispatch(new SearchStep(SearchStep::FETCHING, $listing));

		$found = $fetch();
		$hits = count($found);
		$paging = Paging::of($hits, $request->query['p'] ?? null, match ($listing->showAs()) {
			Listing::POSTS	=> $this->visitor->postsPerPage(),
			Listing::TOPICS	=> $this->visitor->topicsPerPage(),
			default			=> 0,
		});
		$page = $paging->slice($found);

		if ($hits > 0)
			$this->events->dispatch(new SearchStep(SearchStep::PAGINATED, $listing, $hits, $paging, $page));

		$this->events->dispatch(new SearchStep(SearchStep::FETCHED, $listing, $hits, $paging, $page));

		if ($hits === 0)
			return $this->noResults($request, $listing->action(), $strings);

		return $this->resultsPage($listing, $hits, $paging, $page, $strings);
	}

	/**
	 * A quick search: what it lists and how its results are read, or the
	 * answer when the visitor may not ask for it.
	 *
	 * @return Response|array{Listing, ?\Closure(): list<ResultPostInterface|ResultTopicInterface|ResultForumInterface>}
	 */
	private function quickSearch(Request $request, string $action, ?int $value): Response|array {
		$groupId = $this->visitor->groupId();
		$posterId = $this->posterId();
		$member = $value ?? 0;

		switch ($action)
		{
			case 'show_new':
				if ($this->visitor->isGuest())
					return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

				$forum = $value ?? -1;

				return array(new Listing($action, Listing::TOPICS, 'search_new_results', $forum),
					fn (): array => $this->results->newTopics($groupId, $this->visitor->lastVisit(), $forum !== -1 ? $forum : null, $posterId));

			case 'show_recent':
				// Seconds ago, kept within the epoch so the subtraction stays an int
				$since = time() - max(0, min(time(), $value ?? 0));

				return array(new Listing($action, Listing::TOPICS, 'search_recent_results', $value ?? ''), fn (): array => $this->results->recentTopics($groupId, $since, $posterId));

			case 'show_user_posts':
				return array(new Listing($action, Listing::POSTS, 'search_user_posts', $member), fn (): array => $this->results->userPosts($groupId, $member));

			case 'show_user_topics':
				return array(new Listing($action, Listing::TOPICS, 'search_user_topics', $member), fn (): array => $this->results->userTopics($groupId, $member, $posterId));

			case 'show_subscriptions':
			case 'show_forum_subscriptions':
				// Only an administrator looks at someone else's subscriptions
				if ($this->visitor->isGuest() || (!$this->visitor->isAdministrator() && $this->visitor->id() !== $member))
					return $this->badRequest($request);

				return $action === 'show_subscriptions'
					? array(new Listing($action, Listing::TOPICS, 'search_subscriptions', $member), fn (): array => $this->results->subscribedTopics($groupId, $member, $posterId))
					: array(new Listing($action, Listing::FORUMS, 'search_forum_subscriptions', $member), fn (): array => $this->results->subscribedForums($groupId, $member));

			case 'show_unanswered':
				return array(new Listing($action, Listing::TOPICS, 'search_unanswered', ''), fn (): array => $this->results->unansweredTopics($groupId, $posterId));
		}

		// An action extension code added lists nothing of its own
		return array(new Listing($action, Listing::TOPICS, '', $value ?? ''), null);
	}

	/**
	 * A keyword or author search: the searcher is sent to its stored results,
	 * told why there are none, or left on the form when an observer stops it.
	 *
	 * @param array<string, Html> $strings
	 */
	private function search(Request $request, array $strings): Response {
		$query = $request->query;
		$keywords = is_string($query['keywords'] ?? null) ? (new Html($query['keywords']))->trim()->html : '';
		$author = is_string($query['author'] ?? null) ? (new Html($query['author']))->trim()->html : '';

		if (preg_match('#^[\*%]+$#', $keywords) === 1)
			$keywords = '';

		if (preg_match('#^[\*%]+$#', $author) === 1)
			$author = '';

		if (!self::filled($keywords) && !self::filled($author))
			return $this->messages->respond(self::string($strings, 'No terms'), json: $request->xhr);

		$searchIn = !isset($query['search_in']) || $query['search_in'] === 'all' ? 0 : ($query['search_in'] === 'message' ? 1 : -1);
		$forumIds = isset($query['forum']) && is_array($query['forum']) ? array_values(array_map(self::integer(...), $query['forum'])) : array(-1);

		$criteria = new SearchCriteria(
			$keywords,
			$author,
			$searchIn,
			$forumIds,
			($query['show_as'] ?? null) === Listing::POSTS || !isset($query['show_as']) ? Listing::POSTS : Listing::TOPICS,
			isset($query['sort_by']) ? self::integer($query['sort_by']) : null,
			isset($query['sort_dir']) && $query['sort_dir'] !== 'DESC' ? 'ASC' : 'DESC'
		);

		$start = new SearchCaching(SearchCaching::START, $criteria);
		$this->events->dispatch($start);
		if ($start->stopped())
			return $this->form($request, $strings);

		if (mb_strlen(str_replace(array('*', '%'), '', $author)) < self::MIN_AUTHOR)
			$author = '';

		if (mb_strlen(str_replace(array('*', '%'), '', $keywords)) < $this->words->minimumLength())
			$keywords = '';

		if (!self::filled($keywords) && !self::filled($author))
			return $this->messages->respond(self::string($strings, 'No terms'), json: $request->xhr);

		$keywords = mb_strtolower($keywords);
		$author = mb_strtolower($author);

		$since = time() - ($this->visitor->lastSearchAt() ?? 0);
		if ($this->visitor->lastSearchAt() && $since < $this->visitor->searchFloodInterval() && $since >= 0)
			return $this->messages->respond(Html::format(self::string($strings, 'Search flood'), $this->visitor->searchFloodInterval()), json: $request->xhr);

		$this->searches->markSearched(new SearchMark($this->visitor->isGuest() ? null : $this->visitor->id(), $this->visitor->address(), time()));

		$keywordResults = array();
		if (self::filled($keywords))
		{
			$words = $this->keywordList($keywords);
			if ($words === array())
				return $this->noResults($request, null, $strings);

			$keywordResults = $this->keywordResults($words, $searchIn);
		}

		$authorResults = array();
		if (self::filled($author) && $author !== 'guest' && $author !== mb_strtolower($this->language->text('common', 'Guest')->html))
		{
			$userIds = $this->searches->authorIds(str_replace('*', '%', $author));
			if ($userIds !== array())
				$authorResults = $this->searches->authorPosts($userIds);
		}

		if (self::filled($author) && self::filled($keywords))
			$postIds = array_values(array_intersect($keywordResults, $authorResults));
		else
			$postIds = self::filled($keywords) ? $keywordResults : $authorResults;

		if ($postIds === array())
			return $this->noResults($request, null, $strings);

		$inForums = !in_array(-1, $forumIds, true) || (!$this->settings->enabled('o_search_all_forums') && !$this->visitor->isModerating());
		$hits = $this->searches->readableHits($postIds, $this->visitor->groupId(), $inForums ? $forumIds : null, $criteria->showAs() === Listing::POSTS);

		// Only the searches of whoever is online are worth keeping
		$online = $this->searches->onlineIdents();
		if ($online !== array())
			$this->searches->pruneCache(...$online);

		$id = random_int(1, self::MAX_SEARCH_ID);
		$this->searches->store(new StoredSearch($id, $this->ident(), $hits, $criteria->sortBy(), $criteria->sortDir(), $criteria->showAs()));

		$stored = new SearchCaching(SearchCaching::STORED, $criteria, $id);
		$this->events->dispatch($stored);
		if ($stored->stopped())
			return $this->form($request, $strings);

		return new Response('', 302, array('Location' => str_replace('&amp;', '&', $this->urls->link('search_results', array($id))->html)));
	}

	/**
	 * The words of a keyword search worth looking up, each once, in the order given.
	 *
	 * @return list<string>
	 */
	private function keywordList(string $keywords): array {
		// An apostrophe that is not part of a word, then symbols and runs of whitespace
		$keywords = substr((string) preg_replace('((?<=\W)\'|\'(?=\W))', '', ' '.$keywords.' '), 1, -1);
		$keywords = (string) preg_replace('/[\^\$&\(\)<>`"\|,@_\?%~\+\[\]{}:=\/#\\\\;!\.\s]+/', ' ', $keywords);

		return array_values(array_filter(array_unique(explode(' ', $keywords)), fn (string $word): bool => $this->words->searchable($word)));
	}

	/**
	 * The posts the words match: each word narrows what the words before it
	 * found, "or" widens it and "not" takes away.
	 *
	 * @param list<string> $words
	 * @return list<int>
	 */
	private function keywordResults(array $words, int $searchIn): array {
		$matched = array();
		$counted = 0;
		$matchType = 'and';

		foreach ($words as $word)
		{
			if (in_array($word, array('and', 'or', 'not'), true))
			{
				$matchType = $word;
				continue;
			}

			$found = array();
			foreach ($this->searches->keywordMatches(str_replace('*', '%', $word), $searchIn) as $postId)
			{
				$found[$postId] = true;

				if ($counted === 0 || $matchType === 'or')
					$matched[$postId] = true;
				else if ($matchType === 'not')
					$matched[$postId] = false;
			}

			if ($matchType === 'and' && $counted > 0)
				foreach (array_keys($matched) as $postId)
					if (!isset($found[$postId]))
						$matched[$postId] = false;

			++$counted;
		}

		return array_keys(array_filter($matched));
	}

	/** @param array<string, Html> $strings */
	private function noResults(Request $request, ?string $action, array $strings): Response {
		$showing = new NoResultsShowing($action, Html::format('<a href="%s">%s</a>', $this->urls->link('search'), self::string($strings, 'Perform new search'))->html);
		$this->events->dispatch($showing);

		[$heading, $message] = self::QUICK_SEARCHES[$action ?? ''] ?? array('Search results', 'No hits');

		return $this->messages->respond(self::string($strings, $message), new Html($showing->searchAgain()), self::string($strings, $heading), json: $request->xhr);
	}

	/**
	 * @param list<ResultPostInterface|ResultTopicInterface|ResultForumInterface> $page
	 * @param array<string, Html> $strings
	 */
	private function resultsPage(ListingInterface $listing, int $hits, Paging $paging, array $page, array $strings): Response {
		$headOptions = new Parts();
		$footOptions = new Parts();
		$this->events->dispatch(new ResultsHeadAssembling($listing, $headOptions, $footOptions));

		$option = static function (Parts $options, string $name, Html $link, Html $text): void {
			$options->set($name, Html::format('<span%s><a href="%s">%s</a></span>', new Html($options->isEmpty() ? ' class="first-item"' : ''), $link, $text)->html);
		};

		$first = $page[0];
		$userDefined = self::string($strings, 'User defined search');

		switch ($listing->action())
		{
			case 'show_new':
				$crumb = self::string($strings, 'Topics with new')->html;
				$label = 'Topics found';
				$option($headOptions, 'defined_search', $this->urls->link('search'), $userDefined);
				$option($footOptions, 'mark_all', $this->urls->link('mark_read', array($this->tokens->token('markread'.$this->visitor->id()))), $this->language->text('common', 'Mark all as read'));

				// A forum's new topics link to all of that forum's
				if ($listing->argument() !== -1 && $first instanceof ResultTopicInterface)
					$option($headOptions, 'show_all', $this->urls->link('forum', array($first->forumId())), self::string($strings, 'All Topics'));
				break;

			case 'show_user_posts':
				$poster = $first instanceof ResultPostInterface ? $first->poster() : '';
				$crumb = sprintf(self::string($strings, 'Posts by')->html, $poster);
				$label = 'Posts found';
				$option($headOptions, 'user_topics', $this->urls->link('search_user_topics', Listing::arguments($listing)), Html::format(self::string($strings, 'Topics by'), $poster));
				$option($headOptions, 'defined_search', $this->urls->link('search'), $userDefined);
				break;

			case 'show_user_topics':
				$poster = $first instanceof ResultTopicInterface ? $first->poster() : '';
				$crumb = sprintf(self::string($strings, 'Topics by')->html, $poster);
				$label = 'Topics found';
				$option($headOptions, 'user_posts', $this->urls->link('search_user_posts', Listing::arguments($listing)), Html::format(self::string($strings, 'Posts by'), $poster));
				$option($headOptions, 'defined_search', $this->urls->link('search'), $userDefined);
				break;

			case 'show_recent':
			case 'show_unanswered':
			case 'show_subscriptions':
			case 'show_forum_subscriptions':
				$crumb = self::string($strings, self::QUICK_SEARCHES[$listing->action() ?? ''][0] ?? '')->html;
				$label = $listing->showAs() === Listing::FORUMS ? 'Forums found' : 'Topics found';
				$option($headOptions, 'defined_search', $this->urls->link('search'), $userDefined);
				break;

			default:
				$crumb = self::string($strings, 'Search results')->html;
				$label = $listing->showAs() === Listing::TOPICS ? 'Topics found' : 'Posts found';
				$option($headOptions, 'new_search', $this->urls->link('search'), self::string($strings, 'Perform new search'));
		}

		$itemsInfo = $this->formatter->itemsInfo(self::string($strings, $label), $paging->offset + 1, $paging->last, $hits, $paging->pages);
		$paged = $listing->showAs() !== Listing::FORUMS;
		$tracked = !$this->visitor->isGuest() ? $this->visitor->trackedTopics() : new TrackedTopics();

		$head = new PageHead('search'.$listing->showAs(),
			array(
				new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
				new Crumb($crumb),
			),
			page: $paging->page,
			pageCount: $paged && $paging->pages > 1 ? Html::format($this->language->text('common', 'Page info'), $paging->page, $paging->pages) : null,
			pagePost: $paged ? array('paging' => Html::format('<p class="paging"><span class="pages">%s</span> %s</p>', $this->language->text('common', 'Pages'),
				$this->urls->pagination($paging->pages, $paging->page, $listing->url(), Listing::arguments($listing)))) : array(),
			navigation: $paged ? $this->navigation($listing, $paging) : array(),
			mainTitle: self::string($strings, 'Search options')
		);

		return $this->pages->respond($head, fn (): array => array(
			'main' => $this->resultsMain($listing, $paging, $page, $tracked, $itemsInfo, $headOptions, $footOptions, $strings),
		));
	}

	/** @return array<string, Html> the head's links to the first, previous, next and last pages */
	private function navigation(ListingInterface $listing, Paging $paging): array {
		$page = $this->language->text('common', 'Page');
		$arguments = Listing::arguments($listing);

		$navigation = array();
		if ($paging->page < $paging->pages)
		{
			$navigation['last'] = Html::format('<link rel="last" href="%s" title="%s %s" />', $this->urls->sublink($listing->url(), 'page', $paging->pages, $arguments), $page, $paging->pages);
			$navigation['next'] = Html::format('<link rel="next" href="%s" title="%s %s" />', $this->urls->sublink($listing->url(), 'page', $paging->page + 1, $arguments), $page, $paging->page + 1);
		}

		if ($paging->page > 1)
		{
			$navigation['prev'] = Html::format('<link rel="prev" href="%s" title="%s %s" />', $this->urls->sublink($listing->url(), 'page', $paging->page - 1, $arguments), $page, $paging->page - 1);
			$navigation['first'] = Html::format('<link rel="first" href="%s" title="%s 1" />', $this->urls->link($listing->url(), $arguments), $page);
		}

		return $navigation;
	}

	/**
	 * @param list<ResultPostInterface|ResultTopicInterface|ResultForumInterface> $page
	 * @param array<string, Html> $strings
	 */
	private function resultsMain(ListingInterface $listing, Paging $paging, array $page, TrackedTopics $tracked, Html $itemsInfo, Parts $headOptions, Parts $footOptions, array $strings): Html {
		$start = new ResultsRendering(ResultsRendering::START, $listing, $itemsInfo->html, $headOptions, $footOptions);
		$this->events->dispatch($start);

		$rows = new ResultRows($this->events, $this->visitor, $this->language, $this->settings, $this->urls, $this->formatter);

		$variables = array(
			'start'			=> new Html($start->markup()),
			'headOptions'	=> $headOptions->isEmpty() ? null : new Html($headOptions->join(' ')),
			'footOptions'	=> $footOptions->isEmpty() ? null : new Html($footOptions->join(' ')),
			'itemsInfo'		=> $itemsInfo,
		);

		$variables += match ($listing->showAs()) {
			Listing::POSTS	=> array('rows' => $rows->posts($listing, $paging, $page, $strings)),
			Listing::FORUMS	=> array('rows' => $rows->forums($listing, $page)),
			default			=> $rows->topics($listing, $paging, $page, $tracked, $strings),
		};

		$body = $this->templates->render(self::TEMPLATES.$listing->showAs().'.phtml', $variables);

		$end = new ResultsRendering(ResultsRendering::END, $listing, $itemsInfo->html, $headOptions, $footOptions);
		$this->events->dispatch($end);

		return (new Html($body.$end->markup()))->trim();
	}

	/** @param array<string, Html> $strings */
	private function form(Request $request, array $strings): Response {
		$advanced = isset($request->query['advanced']);
		$everyForum = $this->settings->enabled('o_search_all_forums') || $this->visitor->isModerating();

		$info = new Parts(array(
			'keywords'	=> '<li><span>'.self::string($strings, 'Keywords info')->html.'</span></li>',
			'refine'	=> '<li><span>'.self::string($strings, 'Refine info')->html.'</span></li>',
			'wildcard'	=> '<li><span>'.self::string($strings, 'Wildcard info')->html.'</span></li>',
			'forums'	=> '<li><span>'.self::string($strings, $everyForum ? 'Forum default info' : 'Forum require info')->html.'</span></li>',
		));

		$sort = new Parts(array(
			'post_time'		=> '<option value="0">'.self::string($strings, 'Sort by post time')->html.'</option>',
			'author'		=> '<option value="1">'.self::string($strings, 'Sort by author')->html.'</option>',
			'subject'		=> '<option value="2">'.self::string($strings, 'Sort by subject')->html.'</option>',
			'forum_name'	=> '<option value="3">'.self::string($strings, 'Sort by forum')->html.'</option>',
		));

		$headOptions = new Parts();
		if (!$advanced)
			$headOptions->set('advanced_search', Html::format('<span class="first-item"><a href="%s">%s</a></span>', $this->urls->link('search_advanced'), self::string($strings, 'Advanced search'))->html);

		$head = new PageHead('search', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb($this->language->text('common', 'Search')->html),
		));

		return $this->pages->respond($head, fn (): array => array(
			'main' => $this->formMain(new SearchFormView($advanced, $headOptions, $info, $sort), $everyForum, $strings),
		));
	}

	/** @param array<string, Html> $strings */
	private function formMain(SearchFormView $view, bool $everyForum, array $strings): Html {
		$at = function (string $position) use ($view): void {
			$event = $view->rendering($position);
			$this->events->dispatch($event);
			$view->place($event);
		};

		$at(SearchFormRendering::MAIN_OUTPUT_START);
		$view->showHead();

		$at(SearchFormRendering::PRE_CRITERIA_FIELDSET);
		$view->numberGroup('criteria');
		$at(SearchFormRendering::PRE_KEYWORDS);
		$view->numberItem('keywords');
		$view->numberField('keywords');
		$at(SearchFormRendering::PRE_AUTHOR);

		if ($view->advanced)
		{
			$view->numberItem('author');
			$view->numberField('author');
			$at(SearchFormRendering::PRE_SEARCH_IN);
			$view->numberItem('search_in');
			$view->numberField('search_in');
		}

		$forums = $view->advanced || !$everyForum;
		if ($forums)
		{
			$at(SearchFormRendering::PRE_FORUM_FIELDSET);
			$view->numberItem('forums');
			$at(SearchFormRendering::PRE_FORUM_CHECKLIST);
			$view->show('checklist', $this->checklist($view));
			$at(SearchFormRendering::PRE_FORUM_FIELDSET_END);
		}

		$at(SearchFormRendering::FORUM_FIELDSET_END);
		$at(SearchFormRendering::CRITERIA_FIELDSET_END);
		$view->restartItems();
		$at(SearchFormRendering::PRE_RESULTS_FIELDSET);

		if ($view->advanced)
		{
			$view->numberGroup('results');
			$at(SearchFormRendering::PRE_SORT_BY);
			$view->showSort();
			$view->numberItem('sort_by');
			$view->numberField('sort_by');
			$at(SearchFormRendering::PRE_SORT_ORDER_FIELDSET);
			$view->numberItem('sort_order');
			$at(SearchFormRendering::PRE_SORT_ORDER);
			$view->numberField('ascending');
			$view->numberField('descending');
			$at(SearchFormRendering::PRE_SORT_ORDER_FIELDSET_END);
			$at(SearchFormRendering::PRE_DISPLAY_CHOICES_FIELDSET);
			$view->numberItem('display');
			$at(SearchFormRendering::PRE_DISPLAY_CHOICES);
			$view->numberField('topics');
			$view->numberField('posts');
			$at(SearchFormRendering::NEW_DISPLAY_CHOICES);
			$at(SearchFormRendering::PRE_DISPLAY_CHOICES_FIELDSET_END);
			$at(SearchFormRendering::PRE_RESULTS_FIELDSET_END);
		}

		$at(SearchFormRendering::RESULTS_FIELDSET_END);
		$at(SearchFormRendering::END);

		$view->show('search', $strings);
		$view->show('forumFieldset', $forums);
		$view->show('forumNote', self::string($strings, $everyForum ? 'Forum search default' : 'Forum search require'));
		$view->show('action', $this->urls->link('search'));

		return (new Html($this->templates->render(self::TEMPLATES.'form.phtml', $view->variables())))->trim();
	}

	/**
	 * The boxes of the forums the visitor may search, grouped by category.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function checklist(SearchFormView $view): array {
		$boxes = array();
		$category = 0;

		foreach ($this->results->forums($this->visitor->groupId()) as $forum)
		{
			$start = $view->checklistRendering(ForumChecklistRendering::START, $forum);
			$this->events->dispatch($start);
			$view->placeCounts($start);

			$opens = $forum->categoryId() !== $category;
			$closes = $opens && $category !== 0;
			$category = $forum->categoryId();

			$box = array(
				'start'		=> new Html($start->markup()),
				'opens'		=> $opens,
				'closes'	=> $closes,
				'category'	=> $forum->categoryName(),
				'field'		=> $view->nextField(),
				'id'		=> $forum->id(),
				'name'		=> $forum->name(),
			);

			$end = $view->checklistRendering(ForumChecklistRendering::END, $forum);
			$this->events->dispatch($end);
			$view->placeCounts($end);

			$boxes[] = $box + array('end' => new Html($end->markup()));
		}

		return $boxes;
	}

	private function badRequest(Request $request): Response {
		return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);
	}

	/** Whose stored searches are the visitor's: a member's username, a guest's address. */
	private function ident(): string {
		return $this->visitor->isGuest() ? $this->visitor->address() : $this->visitor->username();
	}

	/** The member a topic says has posted in it, where the board shows that. */
	private function posterId(): ?int {
		return !$this->visitor->isGuest() && $this->settings->enabled('o_show_dot') ? $this->visitor->id() : null;
	}

	/** Whether PHP counts the text as given: '' and '0' are not. */
	private static function filled(string $text): bool {
		return $text !== '' && $text !== '0';
	}

	private static function integer(mixed $value): int {
		return is_scalar($value) ? intval($value) : 0;
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
