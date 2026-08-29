<?php
/**
 * Covers the parts of the upgrade run (.dev/tests/Integration/upgrade_path.php)
 * that can be judged without a database: the fixture it restores, the version
 * rows that make it an upgrade at all, how it reads admin/db_update.php's
 * answers, and the anonymisation the fixture has to keep.
 *
 * The run itself is `make test-upgrade`; this pins the contract it uses, so a
 * fixture that drifts out of the schema, or a guard message that changes
 * wording, fails here instead of silently going unverified.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class UpgradePathTest extends TestCase {
	private static string $fixture = '';

	public static function setUpBeforeClass(): void {
		require_once FORUM_ROOT.'.dev/tests/Integration/upgrade_path.php';

		self::$fixture = (string) file_get_contents(upgrade_path_fixture_file());
	}

	public function testTheFixtureIsCommitted(): void {
		$this->assertFileExists(upgrade_path_fixture_file());
		$this->assertNotSame('', self::$fixture);
	}

	/** A fixture already at the target version gives db_update.php nothing to do. */
	public function testTheFixtureIsOlderThanTheUpdateScriptTargets(): void {
		$target = upgrade_path_target_versions(FORUM_ROOT.'admin/db_update.php');

		$this->assertSame(-1, version_compare(
			upgrade_path_fixture_config(self::$fixture, 'o_cur_version'),
			$target['o_cur_version']
		));
		$this->assertLessThan(
			(int) $target['o_database_revision'],
			(int) upgrade_path_fixture_config(self::$fixture, 'o_database_revision')
		);
	}

	/** A completed update has to satisfy the redirect at include/essentials.php. */
	public function testTheUpdateScriptTargetsThisRelease(): void {
		$this->assertSame(
			array('o_cur_version' => FORUM_VERSION, 'o_database_revision' => (string) FORUM_DB_REVISION),
			upgrade_path_target_versions(FORUM_ROOT.'admin/db_update.php')
		);
	}

	/** A table missing from the fixture is a table the update never touches. */
	public function testTheFixtureCarriesTheWholeSchema(): void {
		preg_match_all('/CREATE TABLE `%PREFIX%([a-z_]+)`/', self::$fixture, $matches);
		$tables = $matches[1];
		sort($tables);

		$this->assertSame(install_matrix_expected_tables(), $tables);
	}

	/** The prefix is what keeps the fixture off the forum installed beside it. */
	public function testTheFixtureIsEntirelyPrefixed(): void {
		$this->assertSame(0, preg_match('/(?:CREATE TABLE|INSERT INTO) `(?!%PREFIX%)/', self::$fixture));

		$claimed = array();
		foreach (install_matrix_drivers() as $spec)
			$claimed[] = $spec['prefix'];

		$this->assertNotContains(UPGRADE_PATH_PREFIX, $claimed);
		$this->assertSame(UPGRADE_PATH_PREFIX, upgrade_path_spec()['prefix']);
	}

	public function testTheFixtureIsAnonymised(): void {
		preg_match_all('/[\w.+-]+@[\w.-]+/', self::$fixture, $emails);
		foreach (array_unique($emails[0]) as $email)
			$this->assertStringEndsWith('@example.invalid', $email, $email.' is not a documentation address');

		// Every IP is from a range reserved for documentation (RFC 5737).
		preg_match_all('/\'(\d+\.\d+\.\d+\.\d+)\'/', self::$fixture, $ips);
		foreach (array_unique($ips[0]) as $ip)
			$this->assertMatchesRegularExpression('/^\'(0\.0\.0\.0|203\.0\.113\.\d+|198\.51\.100\.\d+)\'$/', $ip);
	}

	/** The markers the run asserts on are worthless if the fixture lost them. */
	public function testTheFixtureCarriesEveryMarkerTheRunLooksFor(): void {
		foreach (UPGRADE_PATH_MARKERS as $marker)
			$this->assertStringContainsString($marker, self::$fixture);
	}

	/** 1.4.4 declares its tables utf8mb3: a 4-byte character was never storable. */
	public function testTheFixtureStaysInsideTheBasicMultilingualPlane(): void {
		$this->assertSame(1, preg_match('//u', self::$fixture), 'the fixture is not valid UTF-8');
		$this->assertSame(0, preg_match('/[\x{10000}-\x{10FFFF}]/u', self::$fixture));
	}

	/** base_url moved into config.php in 1.4, so the update must not rewrite it. */
	public function testTheFixtureHasNoBaseUrlRow(): void {
		$this->assertStringNotContainsString('(\'o_base_url\'', self::$fixture);
		$this->assertSame('', upgrade_path_fixture_config(self::$fixture, 'o_base_url'));
	}

	public function testItSplitsTheFixtureIntoStatements(): void {
		$statements = upgrade_path_statements(upgrade_path_fixture_sql(UPGRADE_PATH_PREFIX));

		$this->assertGreaterThan(20, count($statements));
		$this->assertStringStartsWith('CREATE TABLE `'.UPGRADE_PATH_PREFIX.'bans`', $statements[0]);

		foreach ($statements as $statement)
			$this->assertMatchesRegularExpression('/^(CREATE TABLE|INSERT INTO)\b/', $statement);
	}

	/** Post bodies carry apostrophes, semicolons and escapes; none may split. */
	public function testItDoesNotSplitInsideAQuotedString(): void {
		$sql = "-- a comment; not a statement\nINSERT INTO `t` VALUES ('a;b', 'c\\'d;e');\nSELECT 1;";

		$this->assertSame(
			array("INSERT INTO `t` VALUES ('a;b', 'c\\'d;e')", 'SELECT 1'),
			upgrade_path_statements($sql)
		);
	}

	/** The run asserts on a message admin/db_update.php has to keep emitting. */
	public function testItExpectsTheGuardMessageTheUpdateScriptRenders(): void {
		$source = (string) file_get_contents(FORUM_ROOT.'admin/db_update.php');

		$this->assertSame(1, preg_match('/exit\(\'(Your config\.php uses.*?)\'\);/', $source, $match));

		$rendered = str_replace(
			array('\'.$db_type.\'', '\'.$db_replacement.\'', '\\\''),
			array('mysql', 'mysqli', '\''),
			$match[1]
		);

		$this->assertSame($rendered, upgrade_path_removed_driver_message('mysql'));
	}

	public function testItFollowsTheUpdateScriptFromStageToStage(): void {
		$redirect = '<script type="text/javascript">window.location="db_update.php?stage=conv_posts&start_at=300"</script>';

		$this->assertSame('db_update.php?stage=conv_posts&start_at=300', upgrade_path_next_url($redirect));
		$this->assertSame('', upgrade_path_next_url('<p>Your forum database was updated successfully.</p>'));
	}

	public function testItRecognisesTheStartFormAndTheCompletionPage(): void {
		$this->assertTrue(upgrade_path_offers_update('<input type="submit" name="start" value="Start update" />'));
		$this->assertFalse(upgrade_path_offers_update('Your database is already as up-to-date as this script can make it.'));

		$this->assertTrue(upgrade_path_completed('<h1 class="hn"><span>PunBB Database Update completed!</span></h1>'));
		$this->assertFalse(upgrade_path_completed('<h1 class="hn"><span>PunBB Database Update</span></h1>'));
	}

	/** The functional pass logs in as the fixture administrator. */
	public function testTheFixtureAdministratorHashMatchesThePasswordTheRunSends(): void {
		$this->assertSame(1, preg_match(
			'/\(2, 1, \'' . preg_quote(UPGRADE_PATH_USERNAME, '/') . '\', \'(\w{40})\', \'([^\']+)\'/',
			self::$fixture,
			$match
		));

		// forum_hash(): sha1($salt.sha1($password)), which is what 1.4 stored.
		$this->assertSame($match[1], sha1($match[2].sha1(UPGRADE_PATH_PASSWORD)));
	}

	/** The pass reads the fixture extension's hook off the rendered page. */
	public function testTheFixtureHookWritesTheMarkerThePassLooksFor(): void {
		// A disabled extension has no hooks to run, so the marker would prove nothing.
		$this->assertSame(1, preg_match('/\(\'fixture_ext\',.*?, (\d), \'\'\);/s', self::$fixture, $extension));
		$this->assertSame('0', $extension[1], 'the fixture extension must stay enabled');

		$this->assertSame(1, preg_match('/\(\'hd_head\', \'fixture_ext\', \'(.*?)\', \d+/', self::$fixture, $match));
		$this->assertStringContainsString(
			UPGRADE_PATH_HOOK_MARKER,
			str_replace('\\\'', '\'', $match[1])
		);
	}

	/** A backup that was there before the run belongs to the checkout. */
	public function testItRemovesOnlyTheConfigBackupsItsOwnRunProduced(): void {
		$known = 'config.old.'.getmypid().'.known.php';
		$fresh = 'config.old.'.getmypid().'.fresh.php';

		// A backup the checkout already carries is a real one, with real
		// credentials in it: pass it as known so the test never unlinks it.
		$preexisting = upgrade_path_config_backups();

		file_put_contents(FORUM_ROOT.$known, '<?php // known');
		file_put_contents(FORUM_ROOT.$fresh, '<?php // fresh');

		try {
			$this->assertSame(array($fresh), array_values(upgrade_path_clear_config_backups(
				array_merge($preexisting, array($known))
			)));
			$this->assertFileExists(FORUM_ROOT.$known);
			$this->assertFileDoesNotExist(FORUM_ROOT.$fresh);
			$this->assertContains($known, upgrade_path_config_backups());
		} finally {
			@unlink(FORUM_ROOT.$known);
			@unlink(FORUM_ROOT.$fresh);
		}
	}

	public function testItReadsAConfigValueOutOfTheFixtureText(): void {
		$this->assertSame('English', upgrade_path_fixture_config(self::$fixture, 'o_default_lang'));
		$this->assertSame('', upgrade_path_fixture_config(self::$fixture, 'o_nothing_of_the_sort'));
	}
}
