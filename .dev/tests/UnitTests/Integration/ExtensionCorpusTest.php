<?php
/**
 * Covers the parts of the extension corpus runner
 * (.dev/tests/Integration/extension_corpus.php) and its probe that can be judged
 * without a running site or a corpus: skipping without one, the storage it
 * claims, how it reads manifests and orders installs, which points count as
 * offered, what the probe records and whom it blames, how pages are read, the
 * config.php switches, the walk and the report.
 *
 * The run itself needs a live forum and a corpus that is not in the repository,
 * so it never gates the build; this pins what it assumes.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\ModuleTree;

class ExtensionCorpusTest extends TestCase {
	private const FIXTURES = FORUM_ROOT.'.dev/tests/fixtures/extensions';

	private string $scratch = '';

	public static function setUpBeforeClass(): void {
		require_once FORUM_ROOT.'.dev/tests/Integration/extension_corpus.php';
		require_once FORUM_ROOT.'.dev/tests/Integration/upgrade_path.php';
	}

	protected function tearDown(): void {
		if ($this->scratch !== '')
			extension_corpus_remove_tree($this->scratch);
	}

	private function scratch(): string {
		$this->scratch = sys_get_temp_dir().'/punbb_corpus_'.bin2hex(random_bytes(6));
		mkdir($this->scratch);
		$this->scratch = (string) realpath($this->scratch);

		return $this->scratch;
	}

	/** @param array<string, string> $manifests folder => manifest.xml body */
	private function corpus(array $manifests): string {
		$corpus = $this->scratch();

		foreach ($manifests as $id => $body)
		{
			mkdir($corpus.'/'.$id);
			file_put_contents($corpus.'/'.$id.'/manifest.xml', $body);
		}

		return $corpus;
	}

	/** @return array{string, int} what the script printed, and its exit status */
	private function runScript(string $script, array $env = array()): array {
		$process = proc_open(array(PHP_BINARY, '-d', 'display_errors=1', '-d', 'html_errors=1', '-d', 'log_errors=0', $script),
			array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, array_merge(getenv(), $env));

		$output = (string) stream_get_contents($pipes[1]).(string) stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		return array($output, proc_close($process));
	}

	public function testWithoutACorpusItSkipsAndTouchesNothing(): void {
		$config = FORUM_ROOT.'config.php';
		$stash = FORUM_ROOT.'.dev/tmp/matrix/config.php.stash';
		$before = array(is_file($config) ? md5_file($config) : null, file_exists($stash));

		list($output, $status) = $this->runScript(FORUM_ROOT.'.dev/tests/Integration/extension_corpus.php', array('PUNBB_TEST_EXTENSIONS_DIR' => ''));

		$this->assertSame(0, $status, $output);
		$this->assertSame("PUNBB_TEST_EXTENSIONS_DIR is unset: no corpus to run, skipped\n", $output);
		$this->assertSame($before, array(is_file($config) ? md5_file($config) : null, file_exists($stash)), 'the skip stashed or rewrote config.php');
	}

	public function testItNamesACorpusItCannotRun(): void {
		$this->assertSame('', extension_corpus_unusable(self::FIXTURES));
		$this->assertStringEndsWith(' is not a directory', extension_corpus_unusable(self::FIXTURES.'/punbb_fixture/manifest.xml'));
		$this->assertStringEndsWith(' holds no folder with a manifest.xml', extension_corpus_unusable($this->scratch()));

		list($output, $status) = $this->runScript(FORUM_ROOT.'.dev/tests/Integration/extension_corpus.php', array('PUNBB_TEST_EXTENSIONS_DIR' => $this->scratch));

		$this->assertSame(2, $status, $output);
	}

	/** Sharing storage with another run would let one teardown drop the other's tables. */
	public function testItGetsStorageOfItsOwn(): void {
		$claimed = array_merge(array(USER_FLOWS_PREFIX), upgrade_path_claimed());
		foreach (array_merge(array_values(install_matrix_drivers()), array_values(extension_flows_drivers())) as $spec)
			$claimed[] = $spec['backend'].'|'.$spec['name'].'|'.$spec['prefix'];

		$this->assertSame(forum_supported_db_types(), array_keys(extension_corpus_drivers()));
		$this->assertArrayHasKey(EXTENSION_CORPUS_DRIVER, extension_corpus_drivers());

		foreach (extension_corpus_drivers() as $spec)
		{
			$own = $spec['backend'].'|'.$spec['name'].'|'.$spec['prefix'];
			$this->assertNotContains($own, $claimed);

			// The teardown drops every table of the prefix: an empty one is the whole shared database.
			if ($spec['backend'] !== 'sqlite3')
			{
				$this->assertNotSame('', $spec['prefix']);
				$this->assertNotContains($spec['prefix'], $claimed);
			}
		}
	}

	public function testTheTeardownRefusesASharedDatabaseWithoutAPrefix(): void {
		$spec = extension_corpus_drivers()['mysqli'];
		$spec['prefix'] = '';
		// install_matrix_drop_schema() must not reach a server either.
		$spec['backend'] = 'none';

		$this->expectException(RuntimeException::class);
		extension_corpus_drop_tables($spec, array());
	}

	public function testItReadsTheFixtureManifests(): void {
		foreach (array('punbb_fixture' => array(), 'punbb_fixture_dep' => array('punbb_fixture')) as $id => $dependencies)
		{
			$manifest = extension_corpus_manifest(self::FIXTURES.'/'.$id);

			$this->assertSame($id, $manifest['id']);
			$this->assertSame('1.0.0', $manifest['version']);
			$this->assertSame('1.5', $manifest['maxtestedon']);
			$this->assertSame($dependencies, $manifest['dependencies']);
			$this->assertSame(array_values(array_unique(extension_flows_manifest_points($id))), $manifest['points']);
			$this->assertSame('', $manifest['error']);
		}
	}

	public function testItReadsPointListsAndDependencyAttributes(): void {
		$corpus = $this->corpus(array(
			'listed' => '<?xml version="1.0" encoding="utf-8"?><extension engine="1.0"><id>listed</id><version>0.1</version><maxtestedon>1.4.2</maxtestedon>'.
				'<dependencies><dependency minversion="1.1">pun_jquery</dependency><dependency>pun_bbcode</dependency></dependencies>'.
				'<hooks><hook id="rg_end, li_end" priority="4"><![CDATA[ echo 1; ]]></hook><hook id="li_end">echo 2;</hook></hooks></extension>',
			'broken' => '<extension><id>broken',
		));
		mkdir($corpus.'/no_manifest');

		$manifests = extension_corpus_discover($corpus);

		$this->assertSame(array('broken', 'listed'), array_keys($manifests));
		$this->assertSame(array('pun_jquery', 'pun_bbcode'), $manifests['listed']['dependencies']);
		$this->assertSame(array('rg_end', 'li_end'), $manifests['listed']['points']);
		$this->assertSame('1.4.2', $manifests['listed']['maxtestedon']);
		$this->assertSame('manifest.xml does not parse', $manifests['broken']['error']);
	}

	public function testItInstallsEachExtensionAfterTheDependenciesTheCorpusCarries(): void {
		$manifest = static fn(array $dependencies): array => array('dependencies' => $dependencies);

		$order = extension_corpus_install_order(array(
			'y' => $manifest(array('x')),
			'c' => $manifest(array('a')),
			'x' => $manifest(array('y')),
			'a' => $manifest(array('b', 'a')),
			'd' => $manifest(array('not_in_the_corpus')),
			'b' => $manifest(array()),
		));

		// b before a before c; a missing dependency holds nothing back; the x/y cycle breaks at x.
		$this->assertSame(array('b', 'a', 'c', 'd', 'x', 'y'), $order);
	}

	public function testTheTreeOffersTheLiveInventoryPoints(): void {
		$points = extension_corpus_tree_points();

		$this->assertTrue(extension_corpus_in_tree($points, 'aex_add_repository_for_pun_jquery'));
		$this->assertFalse(extension_corpus_in_tree($points, 'aex_add_repository_for_'));
		$this->assertFalse(extension_corpus_in_tree($points, 'aex_add_repository_for_pun-jquery'));
		$this->assertFalse(extension_corpus_in_tree($points, 'aex_add_repository_for_Pun_jquery'));
		$this->assertFalse(extension_corpus_in_tree($points, 'nowhere_in_tree'));

		$this->assertGreaterThanOrEqual(1709, count($points));
		$this->assertArrayHasKey('es_essentials', $points);
		$this->assertArrayHasKey('in_qr_get_cats_and_forums', $points);
		$this->assertArrayNotHasKey('punbb_fixture_banner_pre_output', $points);
	}

	public function testItFindsThePointsACorpusExtensionOffers(): void {
		$this->assertSame(
			array('punbb_fixture_banner_pre_output' => array('punbb_fixture')),
			extension_corpus_offered_points(self::FIXTURES, array('punbb_fixture', 'punbb_fixture_dep'))
		);
	}

	public function testThePrefixKeepsLineNumbersAndParses(): void {
		$prefix = extension_corpus_probe_prefix('an_extension', 'it\'s_a_point');

		$this->assertStringNotContainsString("\n", $prefix);
		$this->assertStringContainsString(var_export(realpath(FORUM_ROOT.'.dev/tests/Integration/extension_corpus_probe.php'), true), $prefix);

		PhpToken::tokenize('<?php '.$prefix.'echo 1;', TOKEN_PARSE);
		$this->addToAssertionCount(1);
	}

	/**
	 * The probe driven the way the forum drives it: hook bodies wrapped as
	 * generate_hooks_cache() wraps them, in a process of its own, with a copy of
	 * the probe rooted in a scratch forum so the checkout's log is left alone.
	 */
	public function testTheProbeRecordsWhatFiredAndWhoseCodeRaisedEachDiagnostic(): void {
		$root = $this->scratch();
		mkdir($root.'/.dev/tests/Integration', 0777, true);
		mkdir($root.'/.dev/tmp/extension_corpus', 0777, true);
		mkdir($root.'/extensions/shipped', 0777, true);
		copy(FORUM_ROOT.'.dev/tests/Integration/extension_corpus_probe.php', $root.'/.dev/tests/Integration/extension_corpus_probe.php');
		file_put_contents($root.'/extensions/shipped/functions.php', '<?php $shipped = $undefined_in_shipped_file;');

		$probe = $root.'/.dev/tests/Integration/extension_corpus_probe.php';

		$hook = static fn(string $extension, string $point, string $code): string => var_export(
			'$GLOBALS[\'ext_info_stack\'][] = array(\'id\' => \''.$extension.'\'); $ext_info = $GLOBALS[\'ext_info_stack\'][count($GLOBALS[\'ext_info_stack\']) - 1];'."\n\n".
			'require_once '.var_export($probe, true).'; extension_corpus_probe(\''.$extension.'\', \''.$point.'\'); '.$code."\n\n".
			'array_pop($GLOBALS[\'ext_info_stack\']); $ext_info = empty($GLOBALS[\'ext_info_stack\']) ? array() : $GLOBALS[\'ext_info_stack\'][count($GLOBALS[\'ext_info_stack\']) - 1];', true);

		file_put_contents($root.'/page.php', '<?php'."\n".
			'eval('.$hook('first', 'fn_first', '$in_hook = $undefined_in_hook; require '.var_export($root.'/extensions/shipped/functions.php', true).';').');'."\n".
			'$in_core = $undefined_in_core;'."\n".
			'$suppressed = @$undefined_but_suppressed;'."\n".
			'function core_helper() { undefined_function_in_core(); }'."\n".
			'eval('.$hook('second', 'fn_second', 'core_helper();').');'."\n");

		list($output, $status) = $this->runScript($root.'/page.php');

		$this->assertSame(255, $status, $output);

		$records = array();
		foreach (file($root.'/.dev/tmp/extension_corpus/probe.jsonl', FILE_IGNORE_NEW_LINES) as $line)
			$records[] = json_decode($line, true);

		$this->assertSame(array(
			array('first', 'fn_first', null),
			array('first', null, 'Warning: Undefined variable $undefined_in_hook'),
			array('shipped', null, 'Warning: Undefined variable $undefined_in_shipped_file'),
			array('', null, 'Warning: Undefined variable $undefined_in_core'),
			array('second', 'fn_second', null),
			// Raised in core, but the hook called it: the uncaught error's stack trace names the eval()'d frame.
			array('second', null, 'Fatal error: Uncaught Error: Call to undefined function undefined_function_in_core()'),
		), array_map(static fn(array $record): array => array(
			$record['extension'],
			$record['point'] ?? null,
			isset($record['text']) ? (string) preg_replace('/ in \/.*$/', '', $record['text']) : null,
		), $records));

		// Every diagnostic on the page is one the probe logged, word for word: nothing is counted twice.
		$pairs = extension_corpus_attribute($records, smoke_diagnostics($output), 'the_step', $root.'/extensions/');

		$this->assertCount(4, $pairs);
		$this->assertNotContains('the_step', array_column($pairs, 0));
	}

	public function testTheOwnerIsTheInnermostExtensionCode(): void {
		$extensions = '/forum/extensions/';
		$eval = '/forum/include/functions.php(12) : eval()\'d code';

		$this->assertSame('b', extension_corpus_probe_owner($eval, array(), array(array('id' => 'a'), array('id' => 'b')), array(), $extensions));
		$this->assertSame('installing', extension_corpus_probe_owner('/forum/admin/extensions.php(221) : eval()\'d code', array(), array(), array('id' => 'installing'), $extensions));
		$this->assertSame('', extension_corpus_probe_owner($eval, array(), array(), array(), $extensions));
		$this->assertSame('shipped', extension_corpus_probe_owner('/forum/include/parser.php', array('/forum/include/parser.php', '/forum/extensions/shipped/lib/x.php'), array(array('id' => 'a')), array(), $extensions));
		$this->assertSame('a', extension_corpus_probe_owner('/forum/include/functions.php', array('/forum/include/functions.php', $eval, '/forum/index.php'), array(array('id' => 'a')), array(), $extensions));
		$this->assertSame('', extension_corpus_probe_owner('/forum/include/functions.php', array('/forum/index.php'), array(array('id' => 'a')), array(), $extensions));
		// A point an extension offers from its own file runs the code of whoever attached to it.
		$this->assertSame('attached', extension_corpus_probe_owner('/forum/extensions/offering/functions.php(40) : eval()\'d code', array(), array(array('id' => 'attached')), array(), $extensions));
		$this->assertSame('', extension_corpus_probe_owner('/elsewhere/extensions/other/x.php', array(), array(), array(), $extensions));
	}

	public function testAPageDiagnosticIsBlamedOnlyWhenTheProbeMissedIt(): void {
		$records = array(
			array('extension' => 'hooked', 'point' => 'in_start'),
			array('extension' => 'hooked', 'text' => 'Warning: Undefined array key "x" in /forum/index.php(9) : eval()\'d code on line 2'),
		);
		$pages = array(
			'Warning: Undefined array key &quot;x&quot; in /forum/index.php(9) : eval()&#039;d code on line 2',
			'Deprecated: Something in /forum/extensions/shipped/page.php on line 4',
			'Warning: Other in /forum/extensions/offering/f.php(3) : eval()\'d code on line 1',
			'Notice: Core in /forum/admin/extensions.php on line 7',
			'Notice: Core in /forum/admin/extensions.php  on line 7',
		);

		$this->assertSame(array(
			array('hooked', 'Warning: Undefined array key "x" in /forum/index.php(9) : eval()\'d code on line 2'),
			array('shipped', 'Deprecated: Something in /forum/extensions/shipped/page.php on line 4'),
			array('installing', 'Warning: Other in /forum/extensions/offering/f.php(3) : eval()\'d code on line 1'),
			array('installing', 'Notice: Core in /forum/admin/extensions.php on line 7'),
		), extension_corpus_attribute($records, $pages, 'installing', '/forum/extensions/'));
	}

	public function testTheGateVerdictIsReadOffTheInstallPage(): void {
		require FORUM_ROOT.'lang/English/admin_ext.php';
		$gate = extension_corpus_gate_message();

		$this->assertSame($lang_admin_ext['Maxtestedon error'], $gate);
		$this->assertSame('refused', extension_corpus_gate_verdict('<div class="ct-box info-box"><p>'.$gate.'</p></div>', 'old', $gate));
		$this->assertSame('passes', extension_corpus_gate_verdict('<form class="frm-form" method="post" action="http://forum.test/admin/extensions.php?install=old">', 'old', $gate));
		$this->assertSame('-', extension_corpus_gate_verdict('<p>Bad request. The link you followed is incorrect or outdated.</p>', 'old', $gate));
		$this->assertSame('-', extension_corpus_gate_verdict('<form action="http://forum.test/admin/extensions.php?install=old_but_longer">', 'old', $gate));
	}

	public function testItSwitchesOnTheLinesTheInstallerWrites(): void {
		$installer = PunBB\Module\Setup\Config\ConfigFile::installed(new PunBB\Module\Setup\Config\BoardConfiguration(new PunBB\Module\Setup\Database\DatabaseSettings('mysqli', '', '', '', '', ''), '', 'forum_cookie'));
		$body = "<?php\n\$db_type = 'mysqli';\n";

		foreach (array('FORUM_DISABLE_EXTENSIONS_VERSION_CHECK', 'FORUM_DEBUG') as $constant)
		{
			$this->assertStringContainsString("//define('".$constant."', 1);", $installer);
			$body .= "\n//define('".$constant."', 1);\n";
		}

		$switched = extension_corpus_switch_on($body, 'FORUM_DEBUG');

		$this->assertStringContainsString("\ndefine('FORUM_DEBUG', 1);", $switched);
		$this->assertStringContainsString("//define('FORUM_DISABLE_EXTENSIONS_VERSION_CHECK', 1);", $switched);
		$this->assertSame('', extension_corpus_switch_on("<?php\n\$db_type = 'mysqli';\n", 'FORUM_DEBUG'));
	}

	public function testAnErrorPageFailsTheStepWithWhatItReports(): void {
		global $lang_common;

		$this->assertSame($lang_common['Forum error header'], EXTENSION_CORPUS_ERROR_PAGE);
		$this->assertSame($lang_common['Forum error description'], EXTENSION_CORPUS_ERROR_DESCRIPTION);

		extension_corpus_assert_page(array('status' => 200, 'body' => '<p>An ordinary page</p>'));
		extension_corpus_assert_page(array('status' => 404, 'body' => '<p>Bad request</p>'));

		$body = '<h1>'.EXTENSION_CORPUS_ERROR_PAGE.'</h1><p>'.EXTENSION_CORPUS_ERROR_DESCRIPTION.'</p>'.
			'<p><strong>Database reported:</strong> Unknown column &#039;parent_id&#039; (Errno: 1054).</p>'.
			'<p><strong>Failed query:</strong> <code>UPDATE forums SET a = parent_id</code></p>'.
			'<p class="error_line">The error occurred on line 25 in /forum/admin/extensions.php(221) : eval()&#039;d code</p>';

		foreach (array(503, 200) as $status)
		{
			try
			{
				extension_corpus_assert_page(array('status' => $status, 'body' => $body));
				$this->fail('the error page passed');
			}
			catch (UserFlowsFailure $e)
			{
				$this->assertSame('HTTP '.$status.': Database reported: Unknown column \'parent_id\' (Errno: 1054). Failed query: UPDATE forums SET a = parent_id The error occurred on line 25 in /forum/admin/extensions.php(221) : eval()\'d code', $e->getMessage());
			}
		}
	}

	public function testItQuotesAStringForEachBackend(): void {
		$this->assertSame('\'it\\\'s a \\\\ path\'', extension_corpus_sql_string(array('backend' => 'mysql'), 'it\'s a \\ path'));
		$this->assertSame('\'it\'\'s a \\ path\'', extension_corpus_sql_string(array('backend' => 'pgsql'), 'it\'s a \\ path'));
		$this->assertSame('\'it\'\'s a \\ path\'', extension_corpus_sql_string(array('backend' => 'sqlite3'), 'it\'s a \\ path'));
	}

	public function testEveryStopOfTheWalkIsARoutedPage(): void {
		$router = ModuleRegistry::discover(ModuleTree::core(FORUM_ROOT))->router();

		foreach (extension_corpus_walk() as $entry)
		{
			$this->assertContains($entry[0], array('admin', 'guest'));
			$this->assertNotNull($router->match((string) strtok($entry[1], '?')), $entry[1].' is not routed');

			if (isset($entry[2]))
				$this->assertIsArray($entry[3]);
		}

		$this->assertSame('post.php?tid=1', extension_corpus_walk()[0][1], 'the reply comes first, so the pages after it parse its markup');
	}

	public function testACopyIsRemovedWithoutFollowingLinks(): void {
		$scratch = $this->scratch();
		mkdir($scratch.'/outside');
		file_put_contents($scratch.'/outside/keep.txt', 'keep');

		extension_corpus_copy_tree(self::FIXTURES.'/punbb_fixture', $scratch.'/copy');
		symlink($scratch.'/outside', $scratch.'/copy/link');

		$this->assertFileEquals(self::FIXTURES.'/punbb_fixture/functions.php', $scratch.'/copy/functions.php');
		$this->assertFileEquals(self::FIXTURES.'/punbb_fixture/manifest.xml', $scratch.'/copy/manifest.xml');

		extension_corpus_remove_tree($scratch.'/copy');

		$this->assertDirectoryDoesNotExist($scratch.'/copy');
		$this->assertFileExists($scratch.'/outside/keep.txt');
	}

	public function testTheInstallColumnReadsTheDatabaseNotTheStep(): void {
		$lines = extension_corpus_report(array(
			'order' => array('late'),
			'manifests' => array('late' => array('version' => '1.0', 'points' => array('in_start'), 'error' => '')),
			'extensions' => array('late' => array('gate' => 'passes', 'install' => 'HTTP 503: Failed query', 'installed' => true, 'uninstall' => '')),
			'tree' => array('in_start' => true),
			'offered' => array(),
			'fired' => array(),
			'diagnostics' => array(),
			'pages' => array(),
		));

		$this->assertSame('   late                   1.0       passes   yes      0/1    0            0            yes', $lines[1]);
		$this->assertContains('   install      HTTP 503: Failed query', $lines);
		$this->assertContains('   not fired    in_start', $lines);
	}

	public function testTheReportHasARowPerExtensionAndTheDetailBehindIt(): void {
		$manifest = static fn(string $version, array $points): array => array('version' => $version, 'points' => $points, 'error' => '');

		$lines = extension_corpus_report(array(
			'order' => array('base', 'broken', 'later'),
			'manifests' => array(
				'base' => $manifest('1.0', array('in_start', 'ft_end', 'pun_bbcode_styles_loaded', 'om_offered')),
				'broken' => $manifest('0.1', array('in_start')),
				'later' => $manifest('2.0', array()),
			),
			'extensions' => array(
				'base' => array('gate' => 'refused', 'install' => '', 'uninstall' => ''),
				'broken' => array('gate' => 'passes', 'install' => 'HTTP 503: Database reported: no such column'),
				'later' => array('gate' => '-', 'install' => '', 'uninstall' => 'HTTP 503: Failed query'),
			),
			'tree' => array('in_start' => true, 'ft_end' => true),
			'offered' => array('om_offered' => array('broken')),
			'fired' => array('base' => array('in_start' => true, 'om_offered' => true)),
			'diagnostics' => array(
				'base' => array('Warning: Undefined variable $x in f.php on line 1' => array('install base' => true, 'index.php as admin' => true, 'index.php as guest' => true, 'search.php as guest' => true, 'userlist.php as guest' => true)),
				'' => array('Deprecated: Core in index.php on line 3' => array('index.php as admin' => true)),
			),
			'pages' => array('index.php as guest' => 'HTTP 503: Failed query'),
		));

		$this->assertSame(array(
			'   extension              version   gate     install  fired  not in tree  diagnostics  uninstall',
			'   base                   1.0       refused  yes      2/4    2            1            yes',
			'   broken                 0.1       passes   no       0/1    0            0            -',
			'   later                  2.0       -        yes      0/0    0            0            no',
			'',
			'base',
			'   not fired    ft_end',
			'   not in tree  pun_bbcode_styles_loaded, om_offered (offered by broken)',
			'   Warning: Undefined variable $x in f.php on line 1 [install base, index.php as admin, index.php as guest and 2 more]',
			'',
			'broken',
			'   install      HTTP 503: Database reported: no such column',
			'',
			'later',
			'   uninstall    HTTP 503: Failed query',
			'',
			'forum code',
			'   Deprecated: Core in index.php on line 3 [index.php as admin]',
			'',
			'pages that failed',
			'   index.php as guest: HTTP 503: Failed query',
		), $lines);
	}

	public function testTheComposerScriptRunsTheCorpusAndGatesNothingElse(): void {
		$scripts = json_decode((string) file_get_contents(FORUM_ROOT.'composer.json'), true)['scripts'];

		$this->assertSame('@php .dev/tests/Integration/extension_corpus.php', $scripts['test-extensions']);

		unset($scripts['test-extensions']);
		$this->assertStringNotContainsString('extension_corpus', json_encode($scripts));
	}
}
