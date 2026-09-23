<?php
/**
 * Covers the parts of the upgrade run (.dev/tests/Integration/upgrade_path.php)
 * that can be judged without a database: the fixtures it restores, the version
 * rows that make them an upgrade at all, how it reads admin/db_update.php's
 * answers, and the anonymisation the fixtures have to keep.
 *
 * The run itself is `make test-upgrade`; this pins the contract it uses, so a
 * fixture that drifts out of the schema, or a guard message that changes
 * wording, fails here instead of silently going unverified.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpgradePathTest extends TestCase {
	public static function setUpBeforeClass(): void {
		require_once FORUM_ROOT.'.dev/tests/Integration/upgrade_path.php';
	}

	/** @return array<string, array{string, string}> release, backend */
	public static function fixtures(): array {
		require_once FORUM_ROOT.'.dev/tests/Integration/upgrade_path.php';

		$fixtures = array();
		foreach (UPGRADE_PATH_RELEASES as $release)
			foreach (array('mysql', 'pgsql', 'sqlite3') as $backend)
				$fixtures[$release.' '.$backend] = array($release, $backend);

		return $fixtures;
	}

	private static function fixture(string $release, string $backend): string {
		return (string) file_get_contents(upgrade_path_fixture_file($release, $backend));
	}

	/** Every driver upgrades from every release, so each backend has a fixture of each. */
	#[DataProvider('fixtures')]
	public function testTheFixtureIsCommitted(string $release, string $backend): void {
		$this->assertFileExists(upgrade_path_fixture_file($release, $backend));
		$this->assertNotSame('', self::fixture($release, $backend));
	}

	public function testItUpgradesOnEverySupportedDriver(): void {
		$this->assertSame(forum_supported_db_types(), array_keys(upgrade_path_drivers()));
		$this->assertSame(forum_supported_db_types(), array_keys(upgrade_path_drivers(true)));
	}

	/** A fixture already at the target version gives db_update.php nothing to do. */
	#[DataProvider('fixtures')]
	public function testTheFixtureIsOlderThanTheUpdateScriptTargets(string $release, string $backend): void {
		$fixture = self::fixture($release, $backend);
		$target = upgrade_path_target_versions(FORUM_ROOT);

		$this->assertSame(-1, version_compare(upgrade_path_fixture_config($fixture, 'o_cur_version'), $target['o_cur_version']));
		$this->assertLessThan((int) $target['o_database_revision'], (int) upgrade_path_fixture_config($fixture, 'o_database_revision'));
	}

	/** The version rows each fixture's header names: 1.5.1 its own, the 1.4.4 schema the release before it, as the MySQL dump always had. */
	public function testTheFixturesCarryTheVersionRowsOfTheirRelease(): void {
		foreach (array('mysql', 'pgsql', 'sqlite3') as $backend)
		{
			$this->assertSame(array('1.4.3', '4'), array(upgrade_path_fixture_config(self::fixture('1.4.4', $backend), 'o_cur_version'), upgrade_path_fixture_config(self::fixture('1.4.4', $backend), 'o_database_revision')));
			$this->assertSame(array('1.5.1', '6'), array(upgrade_path_fixture_config(self::fixture('1.5.1', $backend), 'o_cur_version'), upgrade_path_fixture_config(self::fixture('1.5.1', $backend), 'o_database_revision')));
		}
	}

	/** A completed update has to satisfy the redirect at include/essentials.php. */
	public function testTheUpdateScriptTargetsThisRelease(): void {
		$this->assertSame(
			array('o_cur_version' => FORUM_VERSION, 'o_database_revision' => (string) FORUM_DB_REVISION),
			upgrade_path_target_versions(FORUM_ROOT)
		);
	}

	/** A table missing from a fixture is a table the update never touches, but for the record of the data patches 2.0 applies. */
	#[DataProvider('fixtures')]
	public function testTheFixtureCarriesTheWholeSchema(string $release, string $backend): void {
		preg_match_all('/CREATE TABLE `?%PREFIX%([a-z_]+)/', self::fixture($release, $backend), $matches);
		$tables = $matches[1];
		sort($tables);

		$this->assertSame(array_values(array_diff(install_matrix_expected_tables(), array('data_patches'))), $tables);
	}

	/** users.password held the 40 bytes of a SHA-1 until 1.5 widened it for password_hash(). */
	#[DataProvider('fixtures')]
	public function testThePasswordColumnIsAsWideAsTheReleaseMadeIt(string $release, string $backend): void {
		$this->assertSame(1, preg_match('/^\s*`?password`? (\w+\(\d+\))/mi', self::fixture($release, $backend), $match));
		$this->assertSame($release === '1.4.4' ? 'varchar(40)' : 'varchar(255)', strtolower($match[1]));
	}

	/** The prefix is what keeps the fixture off the forum installed beside it. */
	#[DataProvider('fixtures')]
	public function testTheFixtureIsEntirelyPrefixed(string $release, string $backend): void {
		$fixture = self::fixture($release, $backend);

		$this->assertSame(0, preg_match('/(?:CREATE TABLE|INSERT INTO|INDEX|ON) [`"]?(?!%PREFIX%)[a-z]/', $fixture));
		$this->assertSame(0, preg_match('/setval\(\'(?!%PREFIX%)/', $fixture));
	}

	/** Sharing storage with another run would let one teardown drop the other's tables. */
	public function testItGetsStorageOfItsOwn(): void {
		$claimed = array(USER_FLOWS_PREFIX);
		foreach (array_merge(array_values(install_matrix_drivers()), array_values(extension_flows_drivers())) as $spec)
			$claimed[] = $spec['backend'].'|'.$spec['name'].'|'.$spec['prefix'];

		$own = array();
		foreach (array_merge(array_values(upgrade_path_drivers()), array_values(upgrade_path_drivers(true))) as $spec)
		{
			$own[] = $spec['backend'].'|'.$spec['name'].'|'.$spec['prefix'];

			// The teardown drops every table of the prefix: an empty one is the whole shared database.
			if ($spec['backend'] !== 'sqlite3')
			{
				$this->assertNotSame('', $spec['prefix']);
				$this->assertNotContains($spec['prefix'], $claimed);
			}
		}

		$this->assertSame($own, array_values(array_unique($own)));
		$this->assertSame(array(), array_intersect($own, $claimed));
	}

	/** A mysqli_innodb board is the MySQL fixture on InnoDB; the online list keeps its memory engine. */
	public function testAnInnoDbBoardIsTheMysqlFixtureOnInnoDb(): void {
		$sql = upgrade_path_fixture_sql('1.5.1', 'mysqli_innodb', 'up2_');

		$this->assertStringNotContainsString('MyISAM', $sql);
		$this->assertSame(20, substr_count($sql, 'ENGINE=InnoDB') + substr_count($sql, 'ENGINE=MEMORY'));
		$this->assertSame(1, preg_match('/CREATE TABLE `up2_online` .*? ENGINE=MEMORY/s', $sql));
		$this->assertStringContainsString('CREATE TABLE `up2_bans`', $sql);
	}

	#[DataProvider('fixtures')]
	public function testTheFixtureIsAnonymised(string $release, string $backend): void {
		$fixture = self::fixture($release, $backend);

		preg_match_all('/[\w.+-]+@[\w.-]+/', $fixture, $emails);
		foreach (array_unique($emails[0]) as $email)
			$this->assertStringEndsWith('@example.invalid', $email, $email.' is not a documentation address');

		// Every IP is from a range reserved for documentation (RFC 5737).
		preg_match_all('/\'(\d+\.\d+\.\d+\.\d+)\'/', $fixture, $ips);
		foreach (array_unique($ips[0]) as $ip)
			$this->assertMatchesRegularExpression('/^\'(0\.0\.0\.0|203\.0\.113\.\d+|198\.51\.100\.\d+)\'$/', $ip);
	}

	/** The markers the run asserts on are worthless if the fixture lost them. */
	#[DataProvider('fixtures')]
	public function testTheFixtureCarriesEveryMarkerTheRunLooksFor(string $release, string $backend): void {
		foreach (UPGRADE_PATH_MARKERS as $marker)
			$this->assertStringContainsString($marker, self::fixture($release, $backend));
	}

	/** 1.4.4 and 1.5.1 declare their MySQL tables utf8mb3: a 4-byte character was never storable. */
	#[DataProvider('fixtures')]
	public function testTheFixtureStaysInsideTheBasicMultilingualPlane(string $release, string $backend): void {
		$fixture = self::fixture($release, $backend);

		$this->assertSame(1, preg_match('//u', $fixture), 'the fixture is not valid UTF-8');
		$this->assertSame(0, preg_match('/[\x{10000}-\x{10FFFF}]/u', $fixture));
	}

	/** base_url moved into config.php in 1.4, so the update must not rewrite it. */
	#[DataProvider('fixtures')]
	public function testTheFixtureHasNoBaseUrlRow(string $release, string $backend): void {
		$fixture = self::fixture($release, $backend);

		$this->assertStringNotContainsString('(\'o_base_url\'', $fixture);
		$this->assertSame('', upgrade_path_fixture_config($fixture, 'o_base_url'));
	}

	#[DataProvider('fixtures')]
	public function testItSplitsTheFixtureIntoStatements(string $release, string $backend): void {
		$db_type = array('mysql' => 'mysqli', 'pgsql' => 'pgsql', 'sqlite3' => 'sqlite3')[$backend];
		$statements = upgrade_path_statements(upgrade_path_fixture_sql($release, $db_type, 'up_'));

		$this->assertGreaterThan(20, count($statements));
		$this->assertMatchesRegularExpression('/^CREATE TABLE `?up_bans`? \(/', $statements[0]);

		foreach ($statements as $statement)
			$this->assertMatchesRegularExpression('/^(CREATE TABLE|CREATE INDEX|INSERT INTO|SELECT setval)\b/', $statement);
	}

	/** Post bodies carry apostrophes, semicolons and escapes; none may split. */
	public function testItDoesNotSplitInsideAQuotedString(): void {
		$sql = "-- a comment; not a statement\nINSERT INTO `t` VALUES ('a;b', 'c\\'d;e');\nINSERT INTO \"t\" VALUES ('f''g;h', 'i' || char(10) || 'j;k');\nSELECT 1;";

		$this->assertSame(
			array("INSERT INTO `t` VALUES ('a;b', 'c\\'d;e')", "INSERT INTO \"t\" VALUES ('f''g;h', 'i' || char(10) || 'j;k')", 'SELECT 1'),
			upgrade_path_statements($sql)
		);
	}

	/** The run asserts on a message admin/db_update.php has to keep emitting. */
	public function testItExpectsTheGuardMessageTheUpdateScriptRenders(): void {
		$source = (string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Update/Controller/UpdateController.php');

		$this->assertSame(1, preg_match('/Html::format\(\'(Your config\.php uses.*?)\', \$type, \$replacement\)/', $source, $match));

		$rendered = sprintf(str_replace('\\\'', '\'', $match[1]), 'mysql', 'mysqli');

		$this->assertSame($rendered, upgrade_path_removed_driver_message('mysql'));
	}

	/** Each driver that replaced a removed one is started on its predecessor first, so the guard is asserted where it can fire. */
	public function testItKnowsWhichRemovedDriverEachDriverReplaced(): void {
		$this->assertSame(
			array('mysqli' => 'mysql', 'mysqli_innodb' => 'mysql_innodb', 'pgsql' => '', 'sqlite3' => 'sqlite'),
			array_map('upgrade_path_removed_driver', array_combine(forum_supported_db_types(), forum_supported_db_types()))
		);
	}

	public function testItFollowsTheUpdateScriptFromStageToStage(): void {
		$redirect = '<script type="text/javascript">window.location="db_update.php?stage=conv_posts&start_at=300"</script>';

		$this->assertSame('db_update.php?stage=conv_posts&start_at=300', upgrade_path_next_url($redirect));
		$this->assertSame('db_update.php?stage=conv_posts&start_at=300', upgrade_path_next_url('<script type="text/javascript">window.location="db_update.php?stage=conv_posts&start_at=300"</script>'));
		$this->assertSame('db_update.php?stage=patch&patch=Update%3A%3Aconvert_users&start_at=400', upgrade_path_next_url('<script type="text/javascript">window.location="db_update.php?stage=patch\\u0026patch=Update%3A%3Aconvert_users\\u0026start_at=400"</script>'), 'a batched patch escapes its ampersands for the script');
		$this->assertSame('', upgrade_path_next_url('<p>Your forum database was updated successfully.</p>'));
	}

	/** The comparison every upgraded board is held to: a line per table, column, key, index or engine that differs. */
	public function testItNamesEveryDifferenceFromAFreshInstall(): void {
		$table = array('columns' => array('id' => 'integer NOT NULL', 'name' => 'varchar(20) NULL'), 'primary key' => 'id', 'indexes' => array('name_idx (name)'), 'engine' => 'MyISAM');
		$fresh = array('users' => $table, 'posts' => $table);

		$this->assertSame(array(), upgrade_path_schema_diff($fresh, $fresh));

		$upgraded = array('users' => $table, 'old' => $table);
		$upgraded['users']['columns']['name'] = 'varchar(40) NULL';
		$upgraded['users']['columns']['extra'] = 'integer NULL';
		unset($upgraded['users']['columns']['id']);
		$upgraded['users']['primary key'] = '';
		$upgraded['users']['indexes'] = array();
		$upgraded['users']['engine'] = 'InnoDB';

		$this->assertSame(array(
			'table posts is missing',
			'table old is not in a fresh install',
			'users.id is missing',
			'users.extra is not in a fresh install',
			'users.name is varchar(40) NULL, a fresh install has varchar(20) NULL',
			'users primary key: "", a fresh install has "id"',
			'users indexes: [], a fresh install has ["name_idx (name)"]',
			'users engine: "InnoDB", a fresh install has "MyISAM"',
		), upgrade_path_schema_diff($fresh, $upgraded));
	}

	public function testItRecognisesTheStartFormTheCompletionPageAndARefusal(): void {
		$this->assertTrue(upgrade_path_offers_update('<input type="submit" name="start" value="Start update" />'));
		$this->assertFalse(upgrade_path_offers_update('Your database is already as up-to-date as this script can make it.'));

		$this->assertTrue(upgrade_path_completed('<h1 class="hn"><span>PunBB Database Update completed!</span></h1>'));
		$this->assertFalse(upgrade_path_completed('<h1 class="hn"><span>PunBB Database Update</span></h1>'));

		$source = (string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Update/Controller/Update.php');
		$this->assertSame(1, preg_match('/new Html\(\'(Your database is already as up-to-date[^\']*)\'\)/', $source, $match));
		$this->assertTrue(upgrade_path_up_to_date('<p>'.$match[1].'</p>'));
		$this->assertFalse(upgrade_path_up_to_date('<input type="submit" name="start" value="Start update" />'));
	}

	/** The functional pass logs in as the fixture administrator: 1.4 stored a salted SHA-1, 1.5 rehashed it at a sign-in. */
	#[DataProvider('fixtures')]
	public function testTheFixtureAdministratorHashMatchesThePasswordTheRunSends(string $release, string $backend): void {
		$this->assertSame(1, preg_match(
			'/\(2, 1, \'' . preg_quote(UPGRADE_PATH_USERNAME, '/') . '\', \'([^\']+)\', \'([^\']+)\'/',
			self::fixture($release, $backend),
			$match
		));

		if ($release === '1.4.4')
			$this->assertSame($match[1], sha1($match[2].sha1(UPGRADE_PATH_PASSWORD)));
		else
		{
			$this->assertStringStartsWith('$2y$', $match[1]);
			$this->assertTrue(password_verify(UPGRADE_PATH_PASSWORD, $match[1]));
		}
	}

	/** The pass reads the fixture extension's hook off the rendered page. */
	#[DataProvider('fixtures')]
	public function testTheFixtureHookWritesTheMarkerThePassLooksFor(string $release, string $backend): void {
		$fixture = self::fixture($release, $backend);

		// A disabled extension has no hooks to run, so the marker would prove nothing.
		$this->assertSame(1, preg_match('/\(\'fixture_ext\',.*?, (\d), \'\'\);/s', $fixture, $extension));
		$this->assertSame('0', $extension[1], 'the fixture extension must stay enabled');

		$this->assertSame(1, preg_match('/\(\'hd_head\', \'fixture_ext\', \'(.*?)\', \d+/', $fixture, $match));
		$this->assertStringContainsString(
			UPGRADE_PATH_HOOK_MARKER,
			str_replace($backend === 'mysql' ? '\\\'' : '\'\'', '\'', $match[1])
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
		$fixture = self::fixture('1.4.4', 'mysql');

		$this->assertSame('English', upgrade_path_fixture_config($fixture, 'o_default_lang'));
		$this->assertSame('', upgrade_path_fixture_config($fixture, 'o_nothing_of_the_sort'));
	}

	/** The flows switch IDNA on and hooks off through the lines the installer of 1.4 and 1.5 wrote commented out. */
	public function testTheConfigItWritesCarriesTheInstallersCommentedOutDefines(): void {
		$config = upgrade_path_config(upgrade_path_drivers()['pgsql'], 'http://forum.test', 'pgsql');

		$this->assertStringContainsString("//define('FORUM_ENABLE_IDNA', 1);", $config);
		$this->assertNotSame('', extension_flows_disable_hooks($config));
		$this->assertSame('pgsql', install_matrix_config_db_type($config));
		$this->assertSame(1, preg_match('/^\$db_prefix = \'up3_\';$/m', $config));
	}
}
