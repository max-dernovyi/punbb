<?php
/**
 * Covers the parts of the extension compatibility pass
 * (.dev/tests/Integration/extension_flows.php) that can be judged without a
 * running site: the storage it claims, the fixtures it installs and the hooks
 * it expects them to declare, the request ids and addresses it sends, the
 * config.php switch it flips and how it reads pages and snapshots.
 *
 * The pass itself runs against a live forum; this pins what it assumes, so a
 * fixture that changes its hooks or an installer that stops writing the
 * FORUM_DISABLE_HOOKS line fails here instead of only in the integration run.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class ExtensionFlowsTest extends TestCase {
	public static function setUpBeforeClass(): void {
		require_once FORUM_ROOT.'.dev/tests/Integration/extension_flows.php';
		require_once FORUM_ROOT.'.dev/tests/Integration/upgrade_path.php';
		require_once FORUM_ROOT.'include/xml.php';
	}

	public function testItCoversEverySupportedDriver(): void {
		$this->assertSame(forum_supported_db_types(), array_keys(extension_flows_drivers()));
	}

	/** Sharing storage with another run would let one teardown drop the other's tables. */
	public function testItGetsStorageOfItsOwn(): void {
		$claimed = array(USER_FLOWS_PREFIX, UPGRADE_PATH_PREFIX);
		foreach (install_matrix_drivers() as $spec)
			$claimed[] = $spec['backend'].'|'.$spec['name'].'|'.$spec['prefix'];

		$own = array();
		foreach (extension_flows_drivers() as $spec)
		{
			$own[] = $spec['backend'].'|'.$spec['name'].'|'.$spec['prefix'];

			if ($spec['backend'] !== 'sqlite3')
				$this->assertNotContains($spec['prefix'], $claimed);
		}

		$this->assertSame($own, array_values(array_unique($own)));
		$this->assertSame(array(), array_intersect($own, $claimed));
	}

	/** Each fixture is installed after what it depends on, and its link resolves to it. */
	public function testItInstallsTheFixturesInDependencyOrder(): void {
		$installed = array();

		foreach (EXTENSION_FLOWS_IDS as $id)
		{
			$this->assertDirectoryExists(FORUM_ROOT.'extensions/'.extension_flows_link_target($id));

			$manifest = xml_to_array((string) file_get_contents(EXTENSION_FLOWS_FIXTURES.$id.'/manifest.xml'))['extension'];
			$this->assertSame($id, $manifest['id']);

			// A lone dependency parses to a scalar or to one element array, as admin/extensions.php allows for.
			$dependencies = $manifest['dependencies']['dependency'] ?? array();
			if (!is_array($dependencies) || array_key_exists('content', $dependencies))
				$dependencies = array($dependencies);

			foreach ($dependencies as $dependency)
				$this->assertContains(is_array($dependency) ? $dependency['content'] : $dependency, $installed, $id.' is installed before its dependency');

			$installed[] = $id;
		}
	}

	/** The pass compares extension_hooks against this reading of the manifest, so it has to agree with the parser. */
	public function testItReadsTheHookPointsTheManifestDeclares(): void {
		foreach (EXTENSION_FLOWS_IDS as $id)
		{
			$points = array();
			foreach (xml_to_array((string) file_get_contents(EXTENSION_FLOWS_FIXTURES.$id.'/manifest.xml'))['extension']['hooks']['hook'] as $hook)
				foreach (explode(',', $hook['attributes']['id']) as $point)
					$points[] = trim($point);

			$this->assertSame($points, extension_flows_manifest_points($id));
		}
	}

	/** The walk asserts on exactly these points: a fixture that changes them needs the walk changed too. */
	public function testTheFixturesDeclareThePointsTheWalkAssertsOn(): void {
		$this->assertSame(
			array('vt_modify_topic_info', 'in_qr_get_cats_and_forums', 'fn_get_remote_address_start', 'vt_row_new_post_entry_data', 'in_main_output_start'),
			extension_flows_manifest_points('punbb_fixture')
		);
		$this->assertSame(
			array('punbb_fixture_banner_pre_output', 'fn_get_remote_address_start'),
			extension_flows_manifest_points('punbb_fixture_dep')
		);
	}

	/** The fixture honours X-Punbb-Fixture-Address only for a valid IP. */
	public function testTheAddressesAreOnesTheFixtureAccepts(): void {
		foreach (array(EXTENSION_FLOWS_ADDRESS, EXTENSION_FLOWS_DISABLED_ADDRESS) as $address)
			$this->assertNotFalse(filter_var($address, FILTER_VALIDATE_IP), $address);

		$this->assertNotSame(EXTENSION_FLOWS_ADDRESS, EXTENSION_FLOWS_DISABLED_ADDRESS);
	}

	/** punbb_fixture_mark() cuts the header to 40 characters and strips it to [0-9A-Za-z_-]. */
	public function testRequestIdsSurviveTheFixtureSanitiser(): void {
		foreach (array('index', 'a label far longer than the column could ever hold', 'Ümlaut & spaces!') as $label)
		{
			$id = extension_flows_request_id($label);

			$this->assertLessThanOrEqual(40, strlen($id));
			$this->assertSame($id, preg_replace('/[^0-9A-Za-z_-]/', '', substr($id, 0, 40)));
		}

		$this->assertNotSame(extension_flows_request_id('index'), extension_flows_request_id('index'));
	}

	public function testItSwitchesOnTheLineTheInstallerWrites(): void {
		$this->assertStringContainsString("//define('FORUM_DISABLE_HOOKS', 1);", PunBB\Module\Setup\Config\ConfigFile::installed(new PunBB\Module\Setup\Config\BoardConfiguration(new PunBB\Module\Setup\Database\DatabaseSettings('sqlite3', '', '', '', '', ''), '', 'forum_cookie')));

		$body = "<?php\n\$db_type = 'sqlite3';\n\n// Disable forum hooks (extensions) by removing // from the following line\n//define('FORUM_DISABLE_HOOKS', 1);\n";

		$this->assertSame(str_replace("//define('FORUM_DISABLE_HOOKS'", "define('FORUM_DISABLE_HOOKS'", $body), extension_flows_disable_hooks($body));
		$this->assertSame('', extension_flows_disable_hooks("<?php\n\$db_type = 'sqlite3';\n"));
	}

	public function testItReadsTheMarkersInFiringOrder(): void {
		$markers = array(
			array('extension' => 'punbb_fixture_dep', 'hook' => 'fn_get_remote_address_start', 'seen' => array()),
			array('extension' => 'punbb_fixture_dep', 'hook' => 'punbb_fixture_banner_pre_output', 'seen' => array('banner' => array('punbb_fixture'))),
			array('extension' => 'punbb_fixture', 'hook' => 'in_main_output_start', 'seen' => array()),
		);

		$this->assertSame(array('punbb_fixture_dep:punbb_fixture_banner_pre_output', 'punbb_fixture:in_main_output_start'), extension_flows_sequence($markers));
		$this->assertSame(array($markers[1]), extension_flows_pick($markers, 'punbb_fixture_dep', 'punbb_fixture_banner_pre_output'));
		$this->assertSame(array(), extension_flows_pick($markers, 'punbb_fixture', 'fn_get_remote_address_start'));
	}

	/** An echo counts only between its own post's entry title and the next post's. */
	public function testItPlacesEachEchoInsideItsPost(): void {
		$body = '<h4 id="pc3" class="entry-title hn">Post 3</h4><div class="entry-content">one</div>'.
			'<p class="punbb-fixture-echo" data-post-id="3">punbb_fixture</p>'.
			'<p class="punbb-fixture-echo" data-post-id="4">punbb_fixture</p>'.
			'<h4 id="pc4" class="entry-title hn">Post 4</h4><div class="entry-content">two</div>';

		$offsets = extension_flows_echo_offsets($body, array('3', '4'));

		$this->assertGreaterThan(0, $offsets['3']);
		$this->assertSame(-1, $offsets['4']);
	}

	public function testItNamesEveryDifferenceAnUninstallLeaves(): void {
		$before = array(
			'tables' => array('forums' => array('columns' => array(array('name' => 'id')), 'indexes' => array())),
			'config' => array('o_board_title' => 'Board'),
			'forums' => array(array('id' => '1')),
			'extensions' => array(),
			'extension_hooks' => array(),
		);

		$this->assertSame(array(), extension_flows_snapshot_diff($before, $before));

		$after = $before;
		$after['tables']['punbb_fixture_markers'] = array('columns' => array(), 'indexes' => array());
		$after['tables']['forums']['columns'][] = array('name' => 'punbb_fixture_hidden');
		$after['config']['o_punbb_fixture_banner'] = 'punbb_fixture';
		$after['config']['o_board_title'] = 'Changed';
		$after['extensions'] = array(array('id' => 'punbb_fixture'));

		$differences = extension_flows_snapshot_diff($before, $after);

		$this->assertCount(5, $differences);
		$this->assertContains('table punbb_fixture_markers was left behind', $differences);
		$this->assertContains('config o_punbb_fixture_banner was left behind', $differences);
		$this->assertContains('config o_board_title is \'Changed\', was \'Board\'', $differences);

		unset($after['config']['o_board_title'], $after['tables']['forums']);
		$differences = extension_flows_snapshot_diff($before, $after);

		$this->assertContains('config o_board_title is gone', $differences);
		$this->assertContains('table forums is gone', $differences);
	}

	/** CI runs the pass beside the other integration runs; the corpus runner needs a corpus the repository does not carry. */
	public function testCiRunsThePassAndNotTheCorpus(): void {
		$workflow = (string) file_get_contents(FORUM_ROOT.'.github/workflows/check.yml');

		$this->assertSame(1, preg_match('/^  integration:\n(.*?)(?=^  \S|\z)/ms', $workflow, $job));
		preg_match_all('/^\s+- run: (.+)$/m', $job[1], $runs);

		$this->assertSame(array(
			'php .dev/tests/Integration/install_matrix.php',
			'php .dev/tests/Integration/upgrade_path.php',
			'php .dev/tests/Integration/user_flows.php',
			'php .dev/tests/Integration/extension_flows.php',
		), $runs[1]);

		$this->assertStringNotContainsString('extension_corpus', $workflow);
		$this->assertStringNotContainsString('test-extensions', $workflow);
	}

	public function testEveryStepIsCallable(): void {
		$steps = extension_flows_steps();

		$this->assertNotSame(array(), $steps);

		foreach ($steps as $step)
			$this->assertTrue(function_exists($step[1]), $step[1]);
	}
}
