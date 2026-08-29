<?php
/**
 * Pins the Loader's rendering contract.
 *
 * The JS renderer, the CSS renderer and the option/sort handling of
 * add_js()/add_css() are asserted here, plus a fixture holding the exact
 * markup a canonical page emits. The fixture's history is the LABjs removal:
 * its previous revision is the $LAB chain the forum used to ship.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LoaderTest extends TestCase {
	/** The markup render_js() emits for the canonical page below. */
	private const RENDER_FIXTURE = __DIR__.'/fixtures/render_js.txt';

	private Loader $loader;

	protected function setUp(): void {
		$this->loader = self::freshLoader();
		$GLOBALS['forum_hooks'] = array();
	}

	/** The Loader is a process-wide singleton — do not leak it to the next class. */
	protected function tearDown(): void {
		$instance = new ReflectionProperty(Loader::class, 'instance');
		$instance->setValue(null, null);
		$GLOBALS['forum_hooks'] = array();
	}

	// -- add_js -------------------------------------------------------------

	public function testAddJsAppliesTheDefaultOptions(): void {
		$libs = $this->loader->add_js('http://localhost/a.js');

		$this->assertSame(
			array(
				'type'         => 'url',
				'async'        => false,
				'weight'       => 100,
				'group'        => FORUM_JS_GROUP_DEFAULT,
				'every_page'   => false,
				'defer'        => false,
				'preprocess'   => true,
				'data'         => 'http://localhost/a.js',
			),
			$libs['http://localhost/a.js']
		);
	}

	public function testAddJsKeepsTheOptionsItIsGiven(): void {
		$libs = $this->loader->add_js('code();', array(
			'type'       => 'inline',
			'async'      => true,
			'weight'     => 50,
			'group'      => FORUM_JS_GROUP_SYSTEM,
			'every_page' => true,
			'defer'      => true,
			'preprocess' => false,
		));

		$lib = $libs[0];
		$this->assertSame('inline', $lib['type']);
		$this->assertTrue($lib['async']);
		$this->assertSame(50, $lib['weight']);
		$this->assertSame(FORUM_JS_GROUP_SYSTEM, $lib['group']);
		$this->assertTrue($lib['every_page']);
		$this->assertTrue($lib['defer']);
		$this->assertFalse($lib['preprocess']);
	}

	/** URLs are keyed by the URL itself, so the same file is only loaded once. */
	public function testAddJsDeduplicatesUrlsAndAppendsInlineCode(): void {
		$this->loader->add_js('http://localhost/a.js');
		$this->loader->add_js('http://localhost/a.js', array('async' => true));
		$this->loader->add_js('one();', array('type' => 'inline'));
		$libs = $this->loader->add_js('two();', array('type' => 'inline'));

		$this->assertCount(3, $libs);
		$this->assertTrue($libs['http://localhost/a.js']['async']);
		$this->assertSame('one();', $libs[0]['data']);
		$this->assertSame('two();', $libs[1]['data']);
	}

	/** Insertion order survives an equal weight: each entry adds 1/1000. */
	public function testAddJsNudgesTheWeightByInsertionOrder(): void {
		$this->loader->add_js('http://localhost/a.js');
		$this->loader->add_js('http://localhost/b.js');
		$libs = $this->loader->add_js('http://localhost/c.js');

		$this->assertSame(100, $libs['http://localhost/a.js']['weight']);
		$this->assertSame(100.001, $libs['http://localhost/b.js']['weight']);
		$this->assertSame(100.002, $libs['http://localhost/c.js']['weight']);
	}

	#[DataProvider('emptyDataProvider')]
	public function testAddJsRejectsEmptyData(?string $data): void {
		$this->assertFalse($this->loader->add_js($data));
		$this->assertSame('', $this->loader->render_js());
	}

	#[DataProvider('emptyDataProvider')]
	public function testAddCssRejectsEmptyData(?string $data): void {
		$this->assertFalse($this->loader->add_css($data));
		$this->assertSame('', $this->loader->render_css());
	}

	/** @return array<string, array{string|null}> */
	public static function emptyDataProvider(): array {
		return array(
			'null'       => array(null),
			'empty'      => array(''),
			'whitespace' => array("  \n\t "),
		);
	}

	// -- add_css ------------------------------------------------------------

	public function testAddCssAppliesTheDefaultOptions(): void {
		$libs = $this->loader->add_css('http://localhost/a.css');

		$this->assertSame(
			array(
				'type'       => 'url',
				'weight'     => 100,
				'group'      => FORUM_CSS_GROUP_DEFAULT,
				'media'      => 'all',
				'every_page' => false,
				'preprocess' => true,
				'browsers'   => array(),
				'noscript'   => false,
				'data'       => 'http://localhost/a.css',
			),
			$libs['http://localhost/a.css']
		);
	}

	// -- render_js_simple ---------------------------------------------------

	public function testSimpleRendererEmitsPlainScriptTags(): void {
		$this->loader->add_js('http://localhost/a.js');
		$this->loader->add_js('inline();', array('type' => 'inline'));

		$this->assertSame(
			'<script src="http://localhost/a.js"></script>'."\n"
			.'<script>inline();</script>'."\n",
			$this->render('render_js_simple')
		);
	}

	#[DataProvider('scriptFlagProvider')]
	public function testSimpleRendererEmitsTheAsyncAndDeferFlags(bool $async, bool $defer, string $expected): void {
		$this->loader->add_js('http://localhost/a.js', array('async' => $async, 'defer' => $defer));

		$this->assertSame(
			'<script'.$expected.' src="http://localhost/a.js"></script>'."\n",
			$this->render('render_js_simple')
		);
	}

	/** @return array<string, array{bool, bool, string}> */
	public static function scriptFlagProvider(): array {
		return array(
			'neither'    => array(false, false, ''),
			'async'      => array(true, false, ' async'),
			'defer'      => array(false, true, ' defer="true"'),
			'both'       => array(true, true, ' async defer="true"'),
		);
	}

	/** The simple renderer ignores the groups — order alone carries them. */
	public function testSimpleRendererIgnoresTheGroups(): void {
		$this->loader->add_js('http://localhost/counter.js', array('group' => FORUM_JS_GROUP_COUNTER));
		$this->loader->add_js('http://localhost/system.js', array('group' => FORUM_JS_GROUP_SYSTEM));

		$this->assertSame(
			'<script src="http://localhost/counter.js"></script>'."\n"
			.'<script src="http://localhost/system.js"></script>'."\n",
			$this->render('render_js_simple')
		);
	}

	// -- render_js ----------------------------------------------------------

	public function testRenderJsIsEmptyWithoutLibs(): void {
		$this->assertSame('', $this->loader->render_js());
	}

	/** There is one renderer left: plain script tags, no loader library. */
	public function testRenderJsEmitsPlainScriptTags(): void {
		$this->loader->add_js('http://localhost/a.js');
		$this->loader->add_js('http://localhost/system.js', array('group' => FORUM_JS_GROUP_SYSTEM));

		$this->assertSame(
			'<script src="http://localhost/system.js"></script>'."\n"
			.'<script src="http://localhost/a.js"></script>'."\n",
			$this->loader->render_js()
		);
	}

	/** Nothing the Loader emits may reference the removed script loader. */
	public function testNoRenderedOutputReferencesLabjs(): void {
		$output = self::canonicalPage()->render_js();

		$this->assertStringNotContainsString('$LAB', $output);
		$this->assertStringNotContainsString('.wait(', $output);
		$this->assertFalse(method_exists(Loader::class, 'render_js_labjs'));
		$this->assertFileDoesNotExist(FORUM_ROOT.'include/js/LAB.src.js');
	}

	/**
	 * What LABjs's .wait() bought: an inline lib runs after the url libs it
	 * depends on. Classic script tags do that natively as long as the inline
	 * tag is emitted after them, and the sort keeps it there.
	 */
	public function testInlineLibsAreEmittedAfterTheScriptsTheyDependOn(): void {
		$this->loader->add_js('PUNBB.timezone.detect();', array('type' => 'inline', 'weight' => 110));
		$this->loader->add_js('http://localhost/include/js/min/punbb.timezone.min.js', array('weight' => 100));

		$output = $this->loader->render_js();

		$script = strpos($output, 'punbb.timezone.min.js');
		$inline = strpos($output, 'PUNBB.timezone.detect();');

		// strpos() returning false would compare as 0 and pass vacuously.
		$this->assertNotFalse($script, 'the script tag is missing');
		$this->assertNotFalse($inline, 'the inline call is missing');
		$this->assertLessThan($inline, $script);
	}

	/**
	 * The hooks the ChangeLog tells LABjs extensions to migrate to. Out of
	 * process: this suite's bootstrap defines FORUM_DISABLE_HOOKS.
	 */
	public function testRenderJsSimpleStartHookCanShortCircuitTheRenderer(): void {
		$this->assertSame('hooked', $this->hookHarness('start'));
	}

	public function testRenderJsSimpleEndHookCanAmendTheOutput(): void {
		$this->assertStringEndsWith('<!-- end -->', $this->hookHarness('end'));
	}

	public function testRenderJsSkipsLibsWhoseDataAHookDisabled(): void {
		$output = $this->hookHarness('disabled_lib');

		$this->assertStringNotContainsString('src=""', $output);
		$this->assertStringNotContainsString('a.js', $output);
		$this->assertStringContainsString('b.js', $output);
	}

	private function hookHarness(string $case): string {
		$output = shell_exec(
			escapeshellarg(PHP_BINARY).' '.
			escapeshellarg(__DIR__.'/loader_hook_harness.php').' '.
			escapeshellarg($case).' 2>&1'
		);

		$this->assertIsString($output, 'the harness produced no output');

		return $output;
	}

	public function testRenderJsSortsByGroupThenEveryPageThenWeight(): void {
		$this->loader->add_js('http://localhost/late.js', array('weight' => 200));
		$this->loader->add_js('http://localhost/early.js', array('weight' => 10));
		$this->loader->add_js('http://localhost/every-page.js', array('weight' => 300, 'every_page' => true));
		$this->loader->add_js('http://localhost/system.js', array('weight' => 900, 'group' => FORUM_JS_GROUP_SYSTEM));

		$output = $this->loader->render_js();

		$this->assertSame(
			array('system.js', 'every-page.js', 'early.js', 'late.js'),
			$this->scriptOrder($output)
		);
	}

	/** Golden file: the exact markup a register.php-shaped page emits. */
	public function testRenderedOutputMatchesTheRecordedFixture(): void {
		$this->assertStringEqualsFile(self::RENDER_FIXTURE, self::canonicalPage()->render_js());
	}

	// -- render_css ---------------------------------------------------------

	public function testCssRendererEmitsLinkAndStyleTags(): void {
		$this->loader->add_css('http://localhost/a.css', array('media' => 'screen'));
		$this->loader->add_css('body {}', array('type' => 'inline'));

		$this->assertSame(
			'<link rel="stylesheet" type="text/css" media="screen" href="http://localhost/a.css" />'."\n"
			.'<style>body {}</style>'."\n",
			$this->loader->render_css()
		);
	}

	public function testCssRendererWrapsNoscriptLibs(): void {
		$this->loader->add_css('http://localhost/a.css', array('noscript' => true));
		$this->loader->add_css('body {}', array('type' => 'inline', 'noscript' => true));

		$this->assertSame(
			'<noscript><link rel="stylesheet" type="text/css" media="all" href="http://localhost/a.css" /></noscript>'."\n"
			.'<noscript><style>body {}</style></noscript>'."\n",
			$this->loader->render_css()
		);
	}

	public function testCssRendererWrapsIeOnlyLibsInDownlevelHiddenComments(): void {
		$this->loader->add_css('http://localhost/ie7.css', array('browsers' => array('IE' => 'lte IE 7', '!IE' => false)));

		$this->assertSame(
			'<!--[if lte IE 7]><link rel="stylesheet" type="text/css" media="all" href="http://localhost/ie7.css" /><![endif]-->'."\n",
			$this->loader->render_css()
		);
	}

	public function testCssRendererLeavesAllBrowserLibsUnwrapped(): void {
		$this->loader->add_css('http://localhost/a.css', array('browsers' => array('IE' => true, '!IE' => true)));

		$this->assertStringNotContainsString('<!--[if', $this->loader->render_css());
	}

	// -- helpers ------------------------------------------------------------

	/** A page carrying one lib of every shape the forum actually adds. */
	private static function canonicalPage(): Loader {
		$loader = self::freshLoader();

		$loader->add_js('var PUNBB={};', array('type' => 'inline', 'weight' => 50, 'group' => FORUM_JS_GROUP_SYSTEM));
		$loader->add_js('http://localhost/include/js/min/punbb.common.min.js', array('weight' => 55, 'async' => false, 'group' => FORUM_JS_GROUP_SYSTEM));
		$loader->add_js('http://localhost/style/Oxygen/responsive-nav.min.js', array('weight' => 55, 'async' => false, 'group' => FORUM_JS_GROUP_SYSTEM));
		$loader->add_js('PUNBB.common.quickjump();', array('type' => 'inline', 'weight' => 60, 'group' => FORUM_JS_GROUP_SYSTEM));
		$loader->add_js('http://localhost/include/js/min/punbb.timezone.min.js');
		$loader->add_js('PUNBB.timezone.detect_on_register_form();', array('type' => 'inline'));
		$loader->add_js('http://localhost/counter.js', array('async' => true, 'group' => FORUM_JS_GROUP_COUNTER));

		return $loader;
	}

	/** A Loader with an empty lib list — the singleton is reset, not cloned. */
	private static function freshLoader(): Loader {
		$instance = new ReflectionProperty(Loader::class, 'instance');
		$instance->setValue(null, null);

		return Loader::singleton();
	}

	/** Calls one of the private render_* methods on the loader under test. */
	private function render(string $method): string {
		return (new ReflectionMethod(Loader::class, $method))->invoke($this->loader);
	}

	/** The basenames of every script the markup loads, in emitted order. */
	private function scriptOrder(string $output): array {
		preg_match_all('#/([a-z0-9._-]+\.js)#i', $output, $matches);

		return $matches[1];
	}
}
