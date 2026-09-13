<?php
/**
 * The fixture extensions from plan 08, installed on a scratch forum, driven
 * through the bridge's runners at their own points: the caller receives what
 * the legacy site hands it — the array, the short-circuit, the firing order,
 * the ext_info_stack depth and the output — and each concrete value is pinned.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once FORUM_ROOT.'.dev/tests/UnitTests/Extensions/ScratchForum.php';

class BridgeFixtureExtensionTest extends TestCase {
	private const HIDDEN_FORUM = 'Bridge hidden forum';

	private static ?ScratchForum $forum = null;

	/** @var array<string, array<string, mixed>> */
	private static array $reports = array();

	public static function setUpBeforeClass(): void {
		if (!class_exists('SQLite3'))
			return;

		self::$forum = new ScratchForum();

		foreach (array('punbb_fixture', 'punbb_fixture_dep') as $id)
		{
			self::$forum->addExtension($id);
			self::$forum->submit('admin/extensions.php', array('install' => $id), array('install_comply' => '1'));
		}

		self::$forum->addScript(__DIR__.'/bridge_fixture_harness.php');
		self::$forum->rows('INSERT INTO forums (forum_name, cat_id, punbb_fixture_hidden) VALUES (\''.self::HIDDEN_FORUM.'\', 1, 1)');
	}

	public static function tearDownAfterClass(): void {
		self::$forum?->remove();
		self::$forum = null;
		self::$reports = array();
	}

	/** @return array<string, mixed> what the caller received, plus the markers the hooks left, in firing order */
	private function report(string $scenario, string $via): array {
		if (self::$forum === null)
			$this->markTestSkipped('the scratch forum needs ext-sqlite3');

		$key = $scenario.'-'.$via;
		if (!isset(self::$reports[$key]))
		{
			$output = self::$forum->request('bridge_fixture_harness.php', array('scenario' => $scenario, 'via' => $via));

			foreach (array('Fatal error', 'Uncaught', 'Warning:', 'Deprecated:', 'Notice:', 'Sorry! The page could not be loaded.') as $marker)
				$this->assertStringNotContainsString($marker, $output, $output);

			$this->assertSame(1, preg_match('/^BRIDGE=(.*)$/m', $output, $match), 'the harness did not report: '.$output);

			$report = json_decode($match[1], true);
			$report['markers'] = array();
			foreach (self::$forum->rows('SELECT extension_id, hook_id, seen FROM punbb_fixture_markers WHERE request_id=\'bridge-'.$key.'\' ORDER BY id') as $row)
				$report['markers'][] = array('extension' => $row['extension_id'], 'hook' => $row['hook_id'], 'seen' => json_decode((string) $row['seen'], true));

			self::$reports[$key] = $report;
		}

		return self::$reports[$key];
	}

	/** @return array<string, array{string}> */
	public static function scenarioProvider(): array {
		return array(
			'a $query point'			=> array('query'),
			'a short-circuit'			=> array('short_circuit'),
			'no short-circuit'			=> array('fall_through'),
			'caller locals'				=> array('locals'),
			'a nested point'			=> array('banner'),
		);
	}

	#[DataProvider('scenarioProvider')]
	public function testTheCallerReceivesWhatTheLegacySiteHandsIt(string $scenario): void {
		$this->assertSame($this->report($scenario, 'legacy'), $this->report($scenario, 'bridge'));
	}

	public function testAQueryPointHandsTheHookAnArrayWhoseChangeQueryBuildRuns(): void {
		$report = $this->report('query', 'bridge');

		$this->assertNull($report['returned']);
		$this->assertSame('(fp.read_forum IS NULL OR fp.read_forum=1) AND f.punbb_fixture_hidden=0', $report['query']['WHERE']);
		$this->assertCount(2, $report['query']['JOINS']);
		$this->assertNotEmpty($report['forums']);
		$this->assertNotContains(self::HIDDEN_FORUM, $report['forums']);
		$this->assertSame(array(array('extension' => 'punbb_fixture', 'hook' => 'in_qr_get_cats_and_forums', 'seen' => array('where' => $report['query']['WHERE']))), $report['markers']);
		$this->assertSame(array(), $report['stack']);
	}

	/** The returning extension's pop is skipped and the one after it never runs, as eval($hook) leaves them. */
	public function testAReturnShortCircuitsThePointAndLeavesTheReturningExtensionOnTheStack(): void {
		$report = $this->report('short_circuit', 'bridge');

		$this->assertSame('198.51.100.7', $report['returned']);
		$this->assertSame(array('punbb_fixture'), $report['stack']);
		$this->assertSame(array(
			array('extension' => 'punbb_fixture', 'hook' => 'fn_get_remote_address_start', 'seen' => array('returned' => '198.51.100.7')),
		), $report['markers']);
	}

	public function testWithoutAReturnEveryExtensionAtThePointRunsAndPopsItself(): void {
		$report = $this->report('fall_through', 'bridge');

		$this->assertNull($report['returned']);
		$this->assertSame(array(), $report['stack']);
		$this->assertSame(array(array('extension' => 'punbb_fixture_dep', 'hook' => 'fn_get_remote_address_start', 'seen' => array())), $report['markers']);
	}

	public function testAPointReadsTheCallersLocalsByName(): void {
		$report = $this->report('locals', 'bridge');

		$this->assertSame(array(
			array('extension' => 'punbb_fixture', 'hook' => 'vt_modify_topic_info', 'seen' => array('id' => 3, 'forum_id' => 2, 'subject' => 'Bridged "topic" & more')),
		), $report['markers']);
		$this->assertSame(3, $report['id']);
	}

	/** Inside the bridged point, the dependent writes to the point punbb_fixture offers, and $ext_info names punbb_fixture again after it. */
	public function testANestedLegacyPointInsideABridgedOneSeesTheDependentsWriteAndTheOuterExtension(): void {
		$report = $this->report('banner', 'bridge');

		$this->assertSame("\t".'<p id="punbb-fixture-banner">punbb_fixture punbb_fixture_dep</p>'."\n", $report['html']);
		$this->assertSame(array(
			array('extension' => 'punbb_fixture_dep', 'hook' => 'punbb_fixture_banner_pre_output', 'seen' => array('banner' => array('punbb_fixture'), 'dependency' => 'punbb_fixture')),
			array('extension' => 'punbb_fixture', 'hook' => 'in_main_output_start', 'seen' => array(
				'parts'			=> array('punbb_fixture', 'punbb_fixture_dep'),
				'ext_info_id'	=> 'punbb_fixture',
				'url'			=> ScratchForum::BASE_URL.'/extensions/punbb_fixture',
			)),
		), $report['markers']);
		$this->assertSame(array(), $report['stack']);
	}

	/** The runner captures into a buffer of its own, so the hook sees one output buffer level more than at the site. */
	public function testAMarkupPointEmitsExactlyWhereTheLegacySiteDid(): void {
		$legacy = $this->report('post', 'legacy');
		$bridge = $this->report('post', 'bridge');

		$echo = "\t\t\t\t\t".'<p class="punbb-fixture-echo" data-post-id="7">punbb_fixture</p>'."\n";
		$this->assertSame("\t\t\t\t\t<div class=\"entry-content\">\n\t\t\t\t\t\t<p>Post body</p>\n\t\t\t\t\t</div>\n".$echo."\t\t\t\t</div>\n", $bridge['html']);
		$this->assertSame($legacy['html'], $bridge['html']);

		$this->assertCount(1, $bridge['markers']);
		$this->assertSame(array('extension' => 'punbb_fixture', 'hook' => 'vt_row_new_post_entry_data'), array_slice($bridge['markers'][0], 0, 2));
		$this->assertSame(7, $bridge['markers'][0]['seen']['post_id']);
		$this->assertSame($legacy['markers'][0]['seen']['ob_level'] + 1, $bridge['markers'][0]['seen']['ob_level']);
		$this->assertSame(array(), $bridge['stack']);
	}
}
