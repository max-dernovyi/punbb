<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Chrome;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Layout\Event\AboutRendering;
use PunBB\Module\Layout\Event\AdminAlertsAssembling;
use PunBB\Module\Layout\Event\BoardElementsAssembling;
use PunBB\Module\Layout\Event\DebugRendering;
use PunBB\Module\Layout\Event\HeadAssembling;
use PunBB\Module\Layout\Event\HeaderAssembled;
use PunBB\Module\Layout\Event\MainElementsAssembling;
use PunBB\Module\Layout\Event\PageRendered;
use PunBB\Module\Layout\Event\ScriptsAssembling;
use PunBB\Module\Layout\Event\VisitElementsAssembling;
use PunBB\Module\Layout\View\Html;

/**
 * One page's chrome on its way out: the regions above the page's content, the
 * page's own, those below, and the page composed from all of them. Each group
 * is built in the order the forum has always built it, with its event where
 * extension code has always run.
 */
final class PageChrome implements ChromeInterface {
	/** Every region a chrome template places, in the order the header and the footer fill them. */
	public const REGIONS = array(
		'local', 'head',
		'page', 'skip', 'title', 'desc', 'navlinks', 'announcement', 'messages', 'maint',
		'welcome', 'visit', 'admod',
		'crumbs_top', 'crumbs_end', 'main_title', 'main_pagepost_top', 'main_pagepost_end', 'main_menu', 'admin_menu', 'admin_submenu',
		'main', 'info', 'qpost',
		'about', 'debug', 'javascript',
	);

	/** The regions a page fills with its own content. */
	public const PAGE_REGIONS = array('main', 'info', 'qpost');

	private const POWERED_BY = '<a href="https://punbb.informer.com/" target="_blank">PunBB</a>';

	private const SUPPORTED_BY = '<a href="https://www.informer.com/" target="_blank">Informer Technologies, Inc</a>';

	/** @var array<string, Html>|null */
	private ?array $header = null;

	/** @var array<string, Html> */
	private array $alerts = array();

	public function __construct(
		private readonly Layout $layout,
		private readonly ChromeSourceInterface $source,
		private readonly EventDispatcher $events
	) {}

	public function source(): ChromeSourceInterface {
		return $this->source;
	}

	/**
	 * The regions above and around the page's content. A region an observer
	 * removed is not in the list.
	 *
	 * @return array<string, Html>
	 */
	public function header(): array {
		if ($this->header !== null)
			return $this->header;

		$source = $this->source;
		$regions = array('local' => Html::format('lang="%s" dir="%s"', $source->languageIdentifier(), $source->languageDirection()));
		$regions['head'] = $this->head();

		$board = $source->board();
		$viewer = $source->viewer();

		$elements = new BoardElementsAssembling(self::strings(array(
			'page'			=> Html::format('id="brd-%s" class="brd-page %s"', $source->pageId(), $source->pageType()),
			'skip'			=> Html::format('<p id="brd-access"><a href="#brd-main">%s</a></p>', $source->text('Skip to content')),
			'title'			=> Html::format('<p id="brd-title"><a href="%s">%s</a></p>', $source->link('index'), $board->title),
			'desc'			=> $board->description !== '' ? Html::format('<p id="brd-desc">%s</p>', $board->description) : new Html(''),
			'navlinks'		=> Html::format("<ul>\n\t\t%s\n\t</ul>", $source->navigation()),
			'announcement'	=> $this->announcement($board, $viewer),
			'messages'		=> Html::format("<div id=\"brd-messages\" class=\"brd\">%s</div>\n", $source->flashMessages()),
			'maint'			=> $viewer->isAdministrator && $board->maintenance ? Html::format('<p id="maint-alert" class="warn">%s</p>', Html::format($source->text('Maintenance warning'), Html::format('<a href="%s">%s</a>', $source->link('admin_settings_maintenance'), $source->text('Maintenance mode')))) : new Html(''),
		)));
		$this->events->dispatch($elements);
		$regions += self::regions($elements, BoardElementsAssembling::REGIONS);

		$regions += $this->visit($viewer);
		$regions += $this->admod($board, $viewer);

		$main = new MainElementsAssembling(self::strings($this->mainElements()));
		$this->events->dispatch($main);
		$regions += self::regions($main, MainElementsAssembling::REGIONS);

		$this->events->dispatch(new HeaderAssembled());

		return $this->header = $regions;
	}

	/**
	 * The regions below the page's content: the about region, the debug footer
	 * when the forum debugs, and the scripts.
	 *
	 * @return array<string, Html>
	 */
	public function footer(): array {
		$source = $this->source;

		$about = $this->aboutAt(AboutRendering::START);
		if ($source->viewer()->canReadBoard && $source->board()->quickjump)
			$about .= $this->aboutAt(AboutRendering::PRE_QUICKJUMP).$source->quickjump()->html;

		$about .= $this->aboutAt(AboutRendering::PRE_COPYRIGHT);

		$version = $source->board()->version;
		$about .= Html::format("\t<p id=\"copyright\">%s</p>\n", Html::format($source->text('Powered by'),
			$version !== null ? Html::format(self::POWERED_BY.' %s', $version) : new Html(self::POWERED_BY),
			new Html(self::SUPPORTED_BY)))->html;
		$about .= $this->aboutAt(AboutRendering::END);

		$regions = array('about' => (new Html($about))->trim());

		if ($source->debugging())
		{
			$debug = $this->debugAt(DebugRendering::START);

			$time = $source->queryTime();
			if ($time !== null)
				$debug .= Html::format("<p id=\"querytime\" class=\"quiet\">%s</p>\n", $time)->html;

			$debug .= ($source->savedQueries() ?? new Html(''))->html;
			$debug .= $this->debugAt(DebugRendering::END);

			$regions['debug'] = (new Html($debug))->trim();
		}

		$source->addInlineScript($this->environment(), 50);
		$source->addScript($source->baseUrl().'/include/js/punbb.common.js', 55);

		$this->events->dispatch(new ScriptsAssembling());

		$regions['javascript'] = $source->scripts();

		return $regions;
	}

	public function alerts(): array {
		$this->header();

		return $this->alerts;
	}

	public function inlineScript(string $code): void {
		$this->source->addPageScript($code);
	}

	/** The rendered page, as the observers of its last moment leave it. */
	public function finish(string $html): string {
		$event = new PageRendered($html);
		$this->events->dispatch($event);

		return $event->html();
	}

	/**
	 * The page: its content composed into its chrome with the regions above and
	 * below it, the header built first if the page did not ask for it.
	 *
	 * @param array<string, Html> $content the page's own regions: main, info, qpost
	 */
	public function close(array $content): string {
		$unknown = array_diff(array_keys($content), self::PAGE_REGIONS);
		if ($unknown !== array())
			throw new ChromeException(sprintf('A page fills the regions %s, not %s', implode(', ', self::PAGE_REGIONS), implode(', ', $unknown)));

		$header = $this->header();
		$footer = $this->footer();

		return $this->finish($this->layout->render(Layout::chrome($this->source->pageId()), $header + $content + $footer));
	}

	private function head(): Html {
		$source = $this->source;
		$head = $source->headEntries();

		if (!$source->allowsIndexing())
			$head['robots'] = new Html('<meta name="ROBOTS" content="NOINDEX, FOLLOW" />');
		else
			$head['descriptions'] = Html::format('<meta name="description" content="%s%s%s" />', $source->crumbs(true), $source->text('Title separator'), $source->board()->description);

		$microid = $source->microid();
		if ($microid !== null)
			$head['microid'] = Html::format('<meta name="microid" content="mailto+http:sha1:%s" />', $microid);

		$head['title'] = Html::format('<title>%s</title>', $source->crumbs(true));

		$rss = $source->feed('rss');
		$atom = $source->feed('atom');
		if ($rss !== null && $atom !== null)
		{
			$head['rss'] = Html::format('<link rel="alternate" type="application/rss+xml" href="%s" title="RSS" />', $rss);
			$head['atom'] = Html::format('<link rel="alternate" type="application/atom+xml" href="%s" title="ATOM" />', $atom);
		}

		$navigation = $source->pageNavigation();
		if ($navigation !== array())
			$head['nav'] = Html::join("\n", $navigation);

		$viewer = $source->viewer();
		if ($viewer->canReadBoard && $viewer->canSearch)
		{
			$head['search'] = Html::format('<link rel="search" type="text/html" href="%s" title="%s" />', $source->link('search'), $source->text('Search'));
			$head['opensearch'] = Html::format('<link rel="search" type="application/opensearchdescription+xml" href="%s" title="%s" />', $source->link('opensearch'), $source->board()->title);
		}

		$head['author'] = Html::format('<link rel="author" type="text/html" href="%s" title="%s" />', $source->link('users'), $source->text('User list'));

		foreach ($source->themeHead() as $number => $line)
			$head['style'.$number] = $line;

		$event = new HeadAssembling(self::strings($head));
		$this->events->dispatch($event);

		$entries = array();
		foreach ($event->names() as $name)
			$entries[] = (string) $event->entry($name);

		return new Html(implode("\n", $entries).$source->stylesheets()->html);
	}

	private function announcement(Board $board, Viewer $viewer): Html {
		if (!$board->announcement || !$viewer->canReadBoard)
			return new Html('');

		return Html::format("<div id=\"brd-announcement\" class=\"gen-content\">%s\n\t<div class=\"content\">%s</div>\n</div>\n",
			$board->announcementHeading !== '' ? Html::format("\n\t<h1 class=\"hn\"><span>%s</span></h1>", $board->announcementHeading) : new Html(''),
			$board->announcementMessage);
	}

	/** @return array<string, Html> the welcome and the visit links */
	private function visit(Viewer $viewer): array {
		$source = $this->source;

		$welcome = $viewer->isGuest
			? Html::format('<p id="welcome"><span>%s</span> <span>%s</span></p>', $source->text('Not logged in'), $source->text('Login nag'))
			: Html::format('<p id="welcome"><span>%s</span></p>', Html::format($source->text('Logged in as'), Html::format('<strong>%s</strong>', $viewer->username)));

		$links = array();
		if ($viewer->canReadBoard && $viewer->canSearch)
		{
			if (!$viewer->isGuest)
				$links['newposts'] = $this->visitLink('visit-new', true, 'search_new', 'New posts');

			$links['recent'] = $this->visitLink('visit-recent', $viewer->isGuest, 'search_recent', 'Active topics');
			$links['unanswered'] = $this->visitLink('visit-unanswered', false, 'search_unanswered', 'Unanswered topics');
		}

		$event = new VisitElementsAssembling($welcome->html, self::strings($links));
		$this->events->dispatch($event);

		$regions = array();
		if ($event->welcome() !== null)
			$regions['welcome'] = new Html((string) $event->welcome());

		$markup = array();
		foreach ($event->names() as $name)
			$markup[] = (string) $event->entry($name);

		$regions['visit'] = $markup !== array() ? new Html('<p id="visit-links" class="options">'.implode(' ', $markup).'</p>') : new Html('');

		return $regions;
	}

	private function visitLink(string $id, bool $first, string $link, string $text): Html {
		$source = $this->source;

		return Html::format('<span id="%s"%s><a href="%s" title="%s">%s</a></span>', $id, new Html($first ? ' class="first-item"' : ''), $source->link($link), $source->text($text.' title'), $source->text($text));
	}

	/** @return array<string, Html> the moderation links, with the administrator's alerts among them */
	private function admod(Board $board, Viewer $viewer): array {
		$source = $this->source;
		$links = array();

		// Only a moderator reads the reports, and only when they are not sent by e-mail alone
		if ($viewer->isModerating && !$board->reportsByEmailOnly && $source->hasUnreadReports())
			$links['reports'] = Html::format('<li id="reports"><a href="%s">%s</a></li>', $source->link('admin_reports'), $source->text('New reports'));

		if ($viewer->isAdministrator)
		{
			$alerts = $this->boardAlerts($board);
			if ($alerts !== array())
				$links['alert'] = Html::format('<li id="alert"><a href="%s">%s</a></li>', $source->link('admin_index'), $source->text('New alerts'));

			$event = new AdminAlertsAssembling(self::strings($links), self::strings($alerts));
			$this->events->dispatch($event);

			$links = array();
			foreach ($event->names() as $name)
				$links[$name] = new Html((string) $event->entry($name));

			foreach ($event->alertNames() as $name)
				$this->alerts[$name] = new Html((string) $event->alert($name));
		}

		$markup = array();
		foreach ($links as $link)
			$markup[] = $link->html;

		return array('admod' => $markup !== array() ? new Html('<ul id="brd-admod">'.implode(' ', $markup).'</ul>') : new Html(''));
	}

	/** @return array<string, Html> */
	private function boardAlerts(Board $board): array {
		$source = $this->source;
		$alerts = array();

		if ($board->maintenance)
			$alerts['maintenance'] = Html::format('<p id="maint-alert" class="warn">%s</p>', $source->text('Maintenance alert'));

		$updates = $board->updates;
		if ($updates !== null)
		{
			$label = $source->text('Updates');

			if ($updates->failed)
				$alerts['update_fail'] = Html::format('<p><strong>%s</strong> %s</p>', $label, $source->text('Updates failed'));
			else if ($updates->version !== null && $updates->hotfix)
				$alerts['update_version_hotfix'] = Html::format('<p><strong>%s</strong> %s</p>', $label, Html::format($source->text('Updates version n hf'), $updates->version, $source->link('admin_extensions_hotfixes')));
			else if ($updates->version !== null)
				$alerts['update_version'] = Html::format('<p><strong>%s</strong> %s</p>', $label, Html::format($source->text('Updates version'), $updates->version));
			else if ($updates->hotfix)
				$alerts['update_hotfix'] = Html::format('<p><strong>%s</strong> %s</p>', $label, Html::format($source->text('Updates hf'), $source->link('admin_extensions_hotfixes')));
		}

		if ($board->databaseIsNewer)
			$alerts['newer_database'] = Html::format('<p><strong>%s</strong> %s</p>', $source->text('Database mismatch'), $source->text('Database mismatch alert'));

		return $alerts;
	}

	/** @return array<string, Html> */
	private function mainElements(): array {
		$source = $this->source;
		$pageId = $source->pageId();
		$regions = array();

		$crumbs = $pageId !== 'index' ? $source->crumbs(false) : null;
		$regions['crumbs_top'] = $crumbs !== null ? Html::format("<div id=\"brd-crumbs-top\" class=\"crumbs\">\n\t<p>%s</p>\n</div>", $crumbs) : new Html('');
		$crumbs = $pageId !== 'index' ? $source->crumbs(false) : null;
		$regions['crumbs_end'] = $crumbs !== null ? Html::format("<div id=\"brd-crumbs-end\" class=\"crumbs\">\n\t<p>%s</p>\n</div>", $crumbs) : new Html('');

		$pages = $source->mainHeadPages();
		$regions['main_title'] = Html::format("<h1 class=\"main-title\">%s%s</h1>\n", $source->mainTitle() ?? $source->lastCrumb(), $pages !== null ? Html::format(' <small>%s</small>', $pages) : new Html(''));

		$pagePost = $source->pagePost();
		$regions['main_pagepost_top'] = $pagePost !== array() ? Html::format("<div id=\"brd-pagepost-top\" class=\"main-pagepost gen-content\">\n\t%s\n</div>", Html::join("\n\t", $pagePost)) : new Html('');
		$regions['main_pagepost_end'] = $pagePost !== array() ? Html::format("<div id=\"brd-pagepost-end\" class=\"main-pagepost gen-content\">\n\t%s\n</div>", Html::join("\n\t", $pagePost)) : new Html('');

		$menu = $source->mainMenu();
		$regions['main_menu'] = $menu !== array() ? Html::format("<div class=\"main-menu gen-content\">\n\t<ul>\n\t\t%s\n\t</ul>\n</div>", Html::join("\n\t\t", $menu)) : new Html('');

		if (Layout::chrome($pageId) === Layout::ADMIN)
		{
			$regions['admin_menu'] = Html::format("<div class=\"admin-menu gen-content\">\n\t<ul>\n\t\t%s\n\t</ul>\n</div>", $source->adminMenu(false));

			$submenu = $source->adminMenu(true);
			$regions['admin_submenu'] = $submenu->html !== '' ? Html::format("<div class=\"admin-submenu gen-content\">\n\t<ul>\n\t\t%s\n\t</ul>\n</div>", $submenu) : new Html('');
		}

		return $regions;
	}

	private function aboutAt(string $position): string {
		$event = new AboutRendering($position);
		$this->events->dispatch($event);

		return $event->markup();
	}

	private function debugAt(string $position): string {
		$event = new DebugRendering($position);
		$this->events->dispatch($event);

		return $event->markup();
	}

	/** The PUNBB.env script every page carries. */
	private function environment(): string {
		$source = $this->source;
		$viewer = $source->viewer();
		$base = Html::script($source->baseUrl())->html;

		return "\n\tif (typeof PUNBB === 'undefined' || !PUNBB) {\n\t\tvar PUNBB = {};\n\t}\n\n\tPUNBB.env = {\n".
			"\t\tbase_url: \"".$base."/\",\n".
			"\t\tbase_js_url: \"".$base."/include/js/\",\n".
			"\t\tuser_lang: \"".Html::script($viewer->language)->html."\",\n".
			"\t\tuser_style: \"".Html::script($viewer->style)->html."\",\n".
			"\t\tuser_is_guest: \"".($viewer->isGuest ? '1' : '0')."\",\n".
			"\t\tpage: \"".Html::script($source->pageId())->html."\"\n".
			"\t};";
	}

	/**
	 * @param array<string, Html> $markup
	 * @return array<string, string>
	 */
	private static function strings(array $markup): array {
		$strings = array();
		foreach ($markup as $name => $html)
			$strings[$name] = $html->html;

		return $strings;
	}

	/**
	 * @param list<string> $names
	 * @return array<string, Html> each of $names the event still carries
	 */
	private static function regions(BoardElementsAssembling|MainElementsAssembling $event, array $names): array {
		$regions = array();
		foreach ($names as $name)
		{
			$markup = $event->entry($name);
			if ($markup !== null)
				$regions[$name] = new Html($markup);
		}

		return $regions;
	}
}
