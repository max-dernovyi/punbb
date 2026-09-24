<?php
/**
 * The layout's chrome, built from a fake source with no forum: which regions a
 * page gets and in what order, the events between them and what an observer
 * changes there, the positions markup is added at, and a page template
 * composed into the chrome template.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Framework\Event\ObserverDeclaration;
use PunBB\Module\Layout\Chrome\ChromeException;
use PunBB\Module\Layout\Chrome\Layout;
use PunBB\Module\Layout\Chrome\PageChrome;
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
use PunBB\Module\Layout\View\TemplateRenderer;

require_once __DIR__.'/FakeChromeSource.php';

class PageChromeTest extends TestCase {
	private const EVENTS = array(
		HeadAssembling::class, BoardElementsAssembling::class, VisitElementsAssembling::class, AdminAlertsAssembling::class,
		MainElementsAssembling::class, HeaderAssembled::class, AboutRendering::class, DebugRendering::class,
		ScriptsAssembling::class, PageRendered::class,
	);

	private FakeChromeSource $source;

	/** @var array<string, list<Closure(EventInterface): void>> event => what observes it, in order */
	private array $observers = array();

	/** @var list<string> every dispatch, as "<event short name>" or "<event short name>:<position>" */
	private array $dispatched = array();

	protected function setUp(): void {
		$this->source = new FakeChromeSource();
	}

	/** @param Closure(EventInterface): void $observe */
	private function observe(string $event, Closure $observe): void {
		$this->observers[$event][] = $observe;
	}

	private function chrome(): PageChrome {
		$declarations = array();
		foreach (self::EVENTS as $event)
		{
			$recorder = new class($this, $event) {
				public function __construct(private PageChromeTest $test, private string $event) {}

				public function observe(EventInterface $event): void {
					$this->test->record($event);
				}
			};

			$declarations[] = new ObserverDeclaration('Probe', $event, $recorder::class, fn (): object => $recorder);
		}

		$events = new EventDispatcher($declarations, new Container(array()));

		return (new Layout($events, new TemplateRenderer()))->open($this->source);
	}

	/** Called by the recording observer: logs the dispatch, then runs the test's observers of the event. */
	public function record(EventInterface $event): void {
		$name = (new ReflectionClass($event))->getShortName();
		$this->dispatched[] = $event instanceof AboutRendering || $event instanceof DebugRendering ? $name.':'.$event->position() : $name;

		foreach ($this->observers[$event::class] ?? array() as $observe)
			$observe($event);
	}

	/** @param array<string, Html> $regions */
	private static function markup(array $regions): array {
		return array_map(static fn (Html $html): string => $html->html, $regions);
	}

	public function testTheHeaderBuildsEachGroupOfRegionsBeforeItsEvent(): void {
		$regions = $this->chrome()->header();

		$this->assertSame(array('HeadAssembling', 'BoardElementsAssembling', 'VisitElementsAssembling', 'MainElementsAssembling', 'HeaderAssembled'), $this->dispatched);
		$this->assertSame(array('local', 'head', 'page', 'skip', 'title', 'desc', 'navlinks', 'announcement', 'messages', 'maint', 'welcome', 'visit', 'admod',
			'crumbs_top', 'crumbs_end', 'main_title', 'main_pagepost_top', 'main_pagepost_end', 'main_menu'), array_keys($regions));

		// The theme's head lines are read before the head's event, the stylesheets after it
		$this->assertSame(array('crumbs', 'theme', 'stylesheets', 'navigation', 'flash', 'crumbs', 'crumbs'), $this->source->reads);
	}

	public function testOnlyAnAdministratorsHeaderRaisesAlertsAndTheAdministrationGetsItsMenus(): void {
		$this->source->viewer = FakeChromeSource::a('admin');
		$this->source->board = FakeChromeSource::boardWith(maintenance: true);
		$this->source->pageId = 'admin-index';

		$this->observe(AdminAlertsAssembling::class, fn (AdminAlertsAssembling $event) => $event->setAlert('probe', '<p>Probe</p>'));

		$chrome = $this->chrome();
		$regions = $chrome->header();

		$this->assertContains('AdminAlertsAssembling', $this->dispatched);
		$this->assertSame(array('maintenance' => '<p id="maint-alert" class="warn">[Maintenance alert]</p>', 'probe' => '<p>Probe</p>'), self::markup($chrome->alerts()));
		$this->assertSame('<ul id="brd-admod"><li id="alert"><a href="/admin_index?a=1&amp;b=2">[New alerts]</a></li></ul>', $regions['admod']->html);
		$this->assertSame("<div class=\"admin-menu gen-content\">\n\t<ul>\n\t\t<li>Start</li>\n\t</ul>\n</div>", $regions['admin_menu']->html);
		$this->assertSame('', $regions['admin_submenu']->html);
	}

	public function testAVisitorWhoIsNotAnAdministratorHasNoAlerts(): void {
		$this->source->board = FakeChromeSource::boardWith(maintenance: true);

		$this->assertSame(array(), $this->chrome()->alerts());
		$this->assertNotContains('AdminAlertsAssembling', $this->dispatched);
	}

	public function testTheRegionsEscapeTheTextTheyShow(): void {
		$regions = self::markup($this->chrome()->header());

		$this->assertSame('lang="en" dir="ltr"', $regions['local']);
		$this->assertSame('id="brd-viewtopic" class="brd-page basic-page"', $regions['page']);
		$this->assertSame('<p id="brd-title"><a href="/index?a=1&amp;b=2">Board &amp; Co</a></p>', $regions['title']);
		$this->assertSame('<p id="brd-desc">About &quot;it&quot;</p>', $regions['desc']);
		$this->assertSame('<p id="welcome"><span>Logged in as <strong>Member &lt;m&gt;</strong>.</span></p>', $regions['welcome']);
		$this->assertSame("<h1 class=\"main-title\">Topic &lt;1&gt;</h1>\n", $regions['main_title']);
		$this->assertSame(
			'<meta name="early" />'."\n".
			'<meta name="ROBOTS" content="NOINDEX, FOLLOW" />'."\n".
			'<title>Topic &lt;1&gt; — Board</title>'."\n".
			'<link rel="search" type="text/html" href="/search?a=1&amp;b=2" title="[Search]" />'."\n".
			'<link rel="search" type="application/opensearchdescription+xml" href="/opensearch?a=1&amp;b=2" title="Board &amp; Co" />'."\n".
			'<link rel="author" type="text/html" href="/users?a=1&amp;b=2" title="[User list]" />'."\n".
			'<link rel="stylesheet" href="oxygen.css" />'."\n",
			$regions['head']
		);
	}

	public function testWhatAnObserverChangesIsWhatTheChromeShows(): void {
		$this->observe(HeadAssembling::class, function (HeadAssembling $event): void {
			$event->remove('early');
			$event->set('title', '<title>Replaced</title>');
			$event->set('late', '<meta name="late" />');
		});
		$this->observe(BoardElementsAssembling::class, fn (BoardElementsAssembling $event) => $event->set('desc', '<p>Changed</p>'));
		$this->observe(VisitElementsAssembling::class, function (VisitElementsAssembling $event): void {
			$event->replaceWelcome(null);
			$event->set('extra', '<span>Extra</span>');
		});
		$this->observe(MainElementsAssembling::class, fn (MainElementsAssembling $event) => $event->remove('crumbs_end'));

		$regions = self::markup($this->chrome()->header());

		$this->assertStringStartsWith('<meta name="ROBOTS" content="NOINDEX, FOLLOW" />'."\n".'<title>Replaced</title>', $regions['head']);
		$this->assertStringContainsString("\n".'<meta name="late" /><link rel="stylesheet"', $regions['head'], 'the entries are joined by newlines, the stylesheets follow the last');
		$this->assertStringNotContainsString('early', $regions['head']);
		$this->assertSame('<p>Changed</p>', $regions['desc']);
		$this->assertArrayNotHasKey('welcome', $regions, 'a region an observer removed is left out, and its marker stays in a legacy template');
		$this->assertStringEndsWith('<span>Extra</span></p>', $regions['visit']);
		$this->assertArrayNotHasKey('crumbs_end', $regions);
	}

	public function testAnEventRefusesARegionItDoesNotCarry(): void {
		$this->expectException(ChromeException::class);

		(new BoardElementsAssembling(array()))->set('crumbs_top', '');
	}

	public function testTheFooterAddsMarkupAtEachPositionOfTheAboutRegion(): void {
		$this->observe(AboutRendering::class, fn (AboutRendering $event) => $event->append('<i>'.$event->position().'</i>'));

		$about = $this->chrome()->footer()['about']->html;

		$this->assertSame('<i>start</i><i>pre_quickjump</i><form id="qjump"></form>'."\n".'<i>pre_copyright</i>'."\t".'<p id="copyright">Powered by <a href="https://punbb.informer.com/" target="_blank">PunBB</a>, supported by <a href="https://www.informer.com/" target="_blank">Informer Technologies, Inc</a>.</p>'."\n".'<i>end</i>', $about);
	}

	public function testWithoutTheJumpListItsPositionIsNeverReached(): void {
		$this->source->board = FakeChromeSource::boardWith(quickjump: false);

		$regions = $this->chrome()->footer();

		$this->assertSame(array('AboutRendering:start', 'AboutRendering:pre_copyright', 'AboutRendering:end', 'ScriptsAssembling'), $this->dispatched);
		$this->assertStringStartsWith('<p id="copyright">', $regions['about']->html, 'the region is trimmed');
		$this->assertArrayNotHasKey('debug', $regions);
		$this->assertNotContains('quickjump', $this->source->reads);
	}

	public function testTheDebugFooterIsThereWhenTheForumDebugs(): void {
		$this->source->debugging = true;
		$this->observe(DebugRendering::class, fn (DebugRendering $event) => $event->append('<b>'.$event->position().'</b>'));

		$this->assertSame('<b>start</b><p id="querytime" class="quiet">Generated in 0.1 seconds</p>'."\n".'<b>end</b>', $this->chrome()->footer()['debug']->html);
	}

	public function testScriptsCanBeRegisteredUntilTheirEventAndAreRenderedAfterIt(): void {
		$this->observe(ScriptsAssembling::class, fn () => $this->source->addScript('late.js', 60));

		$javascript = $this->chrome()->footer()['javascript']->html;

		$this->assertSame('<script>3 scripts</script>', $javascript);
		$this->assertSame('55: http://forum.test/include/js/punbb.common.js', $this->source->scripts[1]);
		$this->assertSame('60: late.js', $this->source->scripts[2]);
		$this->assertStringContainsString("\t\tpage: \"viewtopic\"\n\t};", $this->source->scripts[0]);
	}

	public function testAPageTemplateIsComposedIntoTheChromeAndItsLastMomentMayReplaceIt(): void {
		$main = (new TemplateRenderer())->render(FORUM_ROOT.'.dev/tests/fixtures/templates/topic.phtml', array(
			'subject' => '<b>Subject</b>', 'posts' => array(array('id' => 1, 'message' => 'Hi & bye')), 'closed' => false, 'notice' => '',
		));
		$this->observe(PageRendered::class, fn (PageRendered $event) => $event->replace(str_replace('</body>', '<!-- rendered --></body>', $event->html())));

		$page = $this->chrome()->close(array('main' => new Html($main)));

		$this->assertStringStartsWith("<!DOCTYPE html>\n", $page);
		$this->assertStringContainsString("<div id=\"brd-main\">\n\t\t<h1 class=\"main-title\">Topic &lt;1&gt;</h1>\n", $page);
		$this->assertStringContainsString("<span>&lt;b&gt;Subject&lt;/b&gt;</span>", $page);
		$this->assertStringContainsString('<p class="post" data-id="1">Hi &amp; bye</p>', $page);
		$this->assertStringContainsString('<!-- rendered --></body>', $page);
		$this->assertStringNotContainsString('<!-- forum_', $page, 'a region the page does not fill is empty, not a marker');
		$this->assertSame('PageRendered', end($this->dispatched));
	}

	public function testAPageFillsOnlyItsOwnRegions(): void {
		$this->expectException(ChromeException::class);
		$this->expectExceptionMessage('not head');

		$this->chrome()->close(array('head' => new Html('')));
	}

	public function testThePageIdChoosesTheChrome(): void {
		$this->assertSame(Layout::ADMIN, Layout::chrome('admin-settings-setup'));
		$this->assertSame(Layout::HELP, Layout::chrome('help'));
		$this->assertSame(Layout::MAIN, Layout::chrome('viewtopic'));

		foreach (array(Layout::MAIN, Layout::ADMIN, Layout::HELP) as $chrome)
			$this->assertFileExists(Layout::template($chrome));

		$this->expectException(ChromeException::class);
		Layout::template('../main');
	}

	public function testEveryPageIsSentUncachedAsUtf8Html(): void {
		$this->assertSame(array(
			'Expires'		=> 'Thu, 21 Jul 1977 07:30:00 GMT',
			'Last-Modified'	=> 'Wed, 01 Jan 2020 10:00:00 GMT',
			'Cache-Control'	=> 'post-check=0, pre-check=0',
			'Pragma'		=> 'no-cache',
			'Content-type'	=> 'text/html; charset=utf-8',
		), Layout::headers(1577872800));
	}
}
