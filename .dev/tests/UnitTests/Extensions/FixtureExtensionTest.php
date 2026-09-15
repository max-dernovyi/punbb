<?php
/**
 * The synthetic fixture extensions under .dev/tests/fixtures/extensions/ are
 * sound on their own: every hook body compiles, every point they attach to is
 * one something offers, and punbb_fixture's schema installs and uninstalls on
 * all four drivers.
 *
 * What the hooks observe on a live forum is the integration harness's; this
 * pins the fixture it relies on. mysqli and pgsql need a live server
 * (PUNBB_TEST_MYSQL_* / PUNBB_TEST_PGSQL_*) and skip without one.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\LegacyBridge\Hook\HookMap;

class FixtureExtensionTest extends TestCase {
	private const FIXTURES = FORUM_ROOT.'.dev/tests/fixtures/extensions/';

	/** @var array<string, string> */
	private static array $output = array();

	public static function drivers(): array {
		return array(
			'mysqli'		=> array('mysqli'),
			'mysqli_innodb'	=> array('mysqli_innodb'),
			'pgsql'			=> array('pgsql'),
			'sqlite3'		=> array('sqlite3')
		);
	}

	private static function manifest(string $id): array {
		require_once FORUM_ROOT.'include/xml.php';

		return xml_to_array((string) file_get_contents(self::FIXTURES.$id.'/manifest.xml'))['extension'];
	}

	/** @return list<array{string, string, array<string, string>}> extension id, hook code, attributes */
	private static function hooks(): array {
		$hooks = array();

		foreach (array('punbb_fixture', 'punbb_fixture_dep') as $id)
			foreach (self::manifest($id)['hooks']['hook'] as $hook)
				$hooks[] = array($id, $hook['content'], $hook['attributes']);

		return $hooks;
	}

	/** Runs punbb_fixture's install and uninstall against one driver in a fresh process. */
	private function harness(string $driver): string {
		if (!isset(self::$output[$driver]))
		{
			$command = escapeshellarg(PHP_BINARY).
				' -d display_errors=1 -d error_reporting=-1 '.
				escapeshellarg(__DIR__.'/fixture_install_harness.php').' '.
				escapeshellarg($driver).' 2>&1';

			self::$output[$driver] = (string)shell_exec($command);
		}

		if (strpos(self::$output[$driver], 'NO_SERVER') !== false)
			$this->markTestSkipped('no server for '.$driver);

		$this->assertStringContainsString('DONE', self::$output[$driver], 'the harness died before finishing: '.self::$output[$driver]);

		return self::$output[$driver];
	}

	private function reported(string $output, string $name): string {
		$this->assertSame(1, preg_match('/^'.$name.'=(.*)$/m', $output, $match), $name.' not reported: '.$output);

		return $match[1];
	}

	public function testEveryHookBodyCompiles(): void {
		foreach (self::hooks() as list($id, $code, $attributes))
		{
			try
			{
				token_get_all('<?php '.$code, TOKEN_PARSE);
			}
			catch (ParseError $e)
			{
				$this->fail($id.' at '.$attributes['id'].': '.$e->getMessage());
			}
		}

		$this->addToAssertionCount(1);
	}

	public function testTheInstallAndUninstallCodeCompiles(): void {
		$manifest = self::manifest('punbb_fixture');

		foreach (array('install', 'uninstall') as $section)
			token_get_all('<?php '.$manifest[$section], TOKEN_PARSE);

		$this->addToAssertionCount(1);
	}

	/** A misspelt point is accepted at install and then never fires. A point an event or a plugin covers is offered where it is dispatched. */
	public function testEveryPointTheFixturesAttachToIsOffered(): void {
		$offered = array_keys(HookMap::COVERED);
		$files = array_merge((array) glob(FORUM_ROOT.'*.php'), (array) glob(FORUM_ROOT.'include/*.php'), array(self::FIXTURES.'punbb_fixture/functions.php'));

		foreach ($files as $file)
		{
			preg_match_all('/get_hook\(\'([a-z0-9_]+)\'\)/', (string) file_get_contents($file), $matches);
			$offered = array_merge($offered, $matches[1]);
		}

		foreach (self::hooks() as list($id, $code, $attributes))
			foreach (explode(',', $attributes['id']) as $point)
				$this->assertContains(trim($point), $offered, $id.' attaches to a point nothing offers');
	}

	/** The dependent attaches to the point punbb_fixture offers from its own code. */
	public function testTheDependentAttachesToThePointTheFixtureOffers(): void {
		$points = array();
		foreach (self::hooks() as list($id, $code, $attributes))
			$points[$id][] = $attributes['id'];

		$this->assertStringContainsString("get_hook('punbb_fixture_banner_pre_output')", (string) file_get_contents(self::FIXTURES.'punbb_fixture/functions.php'));
		$this->assertContains('punbb_fixture_banner_pre_output', $points['punbb_fixture_dep']);
		$this->assertNotContains('punbb_fixture_banner_pre_output', $points['punbb_fixture']);
	}

	/** At the short-circuited point the dependent must run after the fixture, whatever the install second. */
	public function testTheDependentRunsAfterTheFixtureAtTheShortCircuitedPoint(): void {
		$priority = array();
		foreach (self::hooks() as list($id, $code, $attributes))
			if ($attributes['id'] == 'fn_get_remote_address_start')
				$priority[$id] = (int) ($attributes['priority'] ?? 5);

		$this->assertCount(2, $priority);
		$this->assertGreaterThan($priority['punbb_fixture'], $priority['punbb_fixture_dep']);
	}

	#[DataProvider('drivers')]
	public function testInstallBuildsTheSchemaAndTheConfig(string $driver): void {
		$output = $this->harness($driver);

		$this->assertSame('true', $this->reported($output, 'MARKERS_TABLE'));
		$this->assertSame('true', $this->reported($output, 'HIDDEN_FIELD'));
		$this->assertSame('punbb_fixture', $this->reported($output, 'CONFIG'));
	}

	/** Quotes, a backslash and a non-ASCII letter survive the marker row intact. */
	#[DataProvider('drivers')]
	public function testAMarkerRoundTripsThroughTheTable(string $driver): void {
		$marker = json_decode($this->reported($this->harness($driver), 'MARKER'), true);

		$this->assertSame('harness-1', $marker['request_id']);
		$this->assertSame('punbb_fixture', $marker['extension_id']);
		$this->assertSame('harness', $marker['hook_id']);
		$this->assertSame(array('subject' => "Ümlaut 'quoted' \\ done"), json_decode($marker['seen'], true));
	}

	#[DataProvider('drivers')]
	public function testTheAddedColumnNarrowsAQuery(string $driver): void {
		$output = $this->harness($driver);

		$this->assertSame('1', $this->reported($output, 'VISIBLE'));
		$this->assertSame('0', $this->reported($output, 'VISIBLE_HIDDEN'));
	}

	#[DataProvider('drivers')]
	public function testReinstallKeepsWhatTheExtensionStored(string $driver): void {
		$output = $this->harness($driver);

		$this->assertSame('1', $this->reported($output, 'REINSTALL_MARKERS'));
		$this->assertSame('1', $this->reported($output, 'REINSTALL_HIDDEN'));
	}

	#[DataProvider('drivers')]
	public function testUninstallPutsTheSchemaAndTheConfigBack(string $driver): void {
		$output = $this->harness($driver);

		$this->assertSame('false', $this->reported($output, 'UNINSTALL_MARKERS_TABLE'));
		$this->assertSame('false', $this->reported($output, 'UNINSTALL_HIDDEN_FIELD'));
		$this->assertSame('(none)', $this->reported($output, 'UNINSTALL_CONFIG'));
		$this->assertSame('1', $this->reported($output, 'UNINSTALL_FORUMS'));
		$this->assertSame($this->reported($output, 'COLUMNS_BEFORE'), $this->reported($output, 'COLUMNS_AFTER'));
	}

	#[DataProvider('drivers')]
	public function testNoDiagnosticReachesTheOutput(string $driver): void {
		$output = $this->harness($driver);

		foreach (array('Fatal error', 'Uncaught', 'Warning:', 'Deprecated:', 'Notice:', 'Sorry! The page could not be loaded.') as $marker)
			$this->assertStringNotContainsString($marker, $output, $output);
	}
}
