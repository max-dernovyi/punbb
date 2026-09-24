<?php
/**
 * The pages outside the board's chrome, the redirect and the maintenance
 * message, built from a fake source with no forum: the regions each places,
 * the language attributes and the debug region the chrome adds, and what a
 * page may not place itself.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Layout\Chrome\ChromeException;
use PunBB\Module\Layout\Chrome\Layout;
use PunBB\Module\Layout\Chrome\PageChrome;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;

require_once __DIR__.'/FakeChromeSource.php';

class BareChromeTest extends TestCase {
	private FakeChromeSource $source;

	private Layout $layout;

	protected function setUp(): void {
		$this->source = new FakeChromeSource();
		$this->layout = new Layout(new EventDispatcher(array(), new Container(array())), new TemplateRenderer());
	}

	public function testEachChromeHasItsTemplateAndRegions(): void {
		$this->assertSame(array('local', 'head', 'main', 'debug'), Layout::regions(Layout::REDIRECT));
		$this->assertSame(array('local', 'head', 'main'), Layout::regions(Layout::MAINTENANCE));
		$this->assertSame(PageChrome::REGIONS, Layout::regions(Layout::ADMIN));
		$this->assertFileExists(Layout::template(Layout::REDIRECT));
		$this->assertFileExists(Layout::template(Layout::MAINTENANCE));

		$this->expectException(ChromeException::class);
		Layout::regions('dialogue');
	}

	public function testTheRedirectPlacesItsRegionsAndTheQueriesTheForumShows(): void {
		$chrome = $this->layout->bare($this->source, Layout::REDIRECT);

		$this->assertEquals(array(new Html('')), $chrome->themeHead());
		$this->assertSame('<link rel="stylesheet" href="oxygen.css" />'."\n", $chrome->stylesheets()->html);

		$page = $chrome->close(array('head' => new Html('<title>Redirecting</title>'), 'main' => new Html('<div id="brd-main">Done</div>')));

		$this->assertStringStartsWith("<!DOCTYPE html>\n<html xml:lang=\"en\" lang=\"en\" dir=\"ltr\">\n<head>\n", $page);
		$this->assertStringContainsString("<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n<title>Redirecting</title>\n</head>\n<body>\n<div id=\"brd-wrap\" class=\"brd-page\">\n<div id=\"brd-redirect\" class=\"brd\">\n<div id=\"brd-main\">Done</div>\n\n</div>\n</div>\n</body>\n</html>", $page);
		$this->assertStringEndsWith('</html>', $page);

		$this->source->savedQueries = new Html('<div id="brd-debug">queries</div>');
		$this->assertStringContainsString("<div id=\"brd-main\">Done</div>\n<div id=\"brd-debug\">queries</div>\n</div>", $chrome->close(array('main' => new Html('<div id="brd-main">Done</div>'))));
	}

	public function testTheMaintenanceMessagePlacesNoDebugRegion(): void {
		$this->source->savedQueries = new Html('<div id="brd-debug">queries</div>');

		$page = $this->layout->bare($this->source, Layout::MAINTENANCE)->close(array('head' => new Html('<link />'), 'main' => new Html("\t<p>Back soon</p>")));

		$this->assertStringContainsString("<link />\n</head>\n<body>\n<div id=\"brd-wrap\" class=\"brd-page\">\n<div id=\"brd-maint\" class=\"brd\">\n\t<p>Back soon</p>\n</div>\n</div>\n</body>\n</html>", $page);
		$this->assertStringNotContainsString('brd-debug', $page);
	}

	public function testAPagePlacesNeitherItsLanguageNorItsDebugRegionNorOneTheChromeLacks(): void {
		$chrome = $this->layout->bare($this->source, Layout::REDIRECT);

		foreach (array('local', 'debug') as $region)
		{
			try {
				$chrome->close(array($region => new Html('')));
				$this->fail('a page placed '.$region);
			}
			catch (ChromeException) {
				$this->addToAssertionCount(1);
			}
		}

		$this->expectException(ChromeException::class);
		$this->layout->bare($this->source, Layout::MAINTENANCE)->close(array('info' => new Html('')));
	}

	public function testABoardChromeIsNotBare(): void {
		$this->expectException(ChromeException::class);
		$this->layout->bare($this->source, Layout::MAIN);
	}
}
