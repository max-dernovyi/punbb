<?php
/**
 * A third-party module's life on a scratch forum, through the front
 * controller: installed with the board or added to a running one, a release
 * unpacked over it, deleted and put back. The fixtures under
 * .dev/tests/fixtures/installed_modules/ are what an administrator unpacks
 * into modules/: Guestbook owns a table, plugs the board index's statistics
 * and observes its rendering; Autograph, whose name sorts first, depends on it.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

require_once FORUM_ROOT.'.dev/tests/UnitTests/Extensions/ScratchForum.php';

class ThirdPartyModuleTest extends TestCase {
	private const OUT_OF_DATE = 'Your PunBB database is out-of-date and must be upgraded in order to continue.';

	private const UP_TO_DATE = 'Your database is already as up-to-date as this script can make it.';

	private static ?ScratchForum $forum = null;

	private static string $log = '';

	public static function setUpBeforeClass(): void {
		self::$forum = new ScratchForum();
		self::$log = (string) tempnam(sys_get_temp_dir(), 'punbb_modules_log_');
		self::$forum->logTo(self::$log);
	}

	public static function tearDownAfterClass(): void {
		self::$forum?->remove();
		self::$forum = null;
		@unlink(self::$log);
	}

	private static function forum(): ScratchForum {
		self::assertNotNull(self::$forum);

		return self::$forum;
	}

	/** @return list<array<string, mixed>> what the board records for the fixture modules */
	private static function versions(ScratchForum $forum): array {
		return $forum->rows('SELECT name, schema_version, data_version FROM modules WHERE name IN (\'Autograph\', \'Guestbook\') ORDER BY name');
	}

	/** @return list<array<string, mixed>> what the board records for the forum's own modules */
	private static function coreVersions(ScratchForum $forum): array {
		return $forum->rows('SELECT name, schema_version, data_version FROM modules WHERE name NOT IN (\'Autograph\', \'Guestbook\') ORDER BY name');
	}

	/** @return array<string, string> every table and index => the SQL that made it */
	private static function schema(ScratchForum $forum): array {
		$schema = array();
		foreach ($forum->rows('SELECT name, sql FROM sqlite_master WHERE sql IS NOT NULL ORDER BY name') as $row)
			$schema[(string) $row['name']] = (string) $row['sql'];

		return $schema;
	}

	/** Runs db_update.php from its start through each patch to its finish; @return string every page */
	private static function update(ScratchForum $forum): string {
		$pages = $page = $forum->requestAsGuest('admin/db_update.php', array('stage' => 'start'));

		$requests = 0;
		while (str_contains($page, 'window.location="db_update.php?stage=patch') && ++$requests <= 10)
			$pages .= $page = $forum->requestAsGuest('admin/db_update.php', array('stage' => 'patch'));

		return $pages.$forum->requestAsGuest('admin/db_update.php', array('stage' => 'finish'));
	}

	public function testAModulePresentAtTheInstallIsInstalledWithTheBoard(): void {
		$forum = new ScratchForum(array('Guestbook' => '1.0.0'));

		try {
			$this->assertSame(array(array('name' => 'Guestbook', 'schema_version' => '1.0.0', 'data_version' => '1.0.0')), self::versions($forum));
			$this->assertArrayHasKey('guestbook', self::schema($forum));
			$this->assertStringContainsString(self::UP_TO_DATE, $forum->requestAsGuest('admin/db_update.php'));
			$this->assertStringContainsString('<p id="guestbook">Guestbook entries: 0</p>', $forum->request('index.php'));
		}
		finally {
			$forum->remove();
		}
	}

	public function testAModuleAddedToARunningBoardWaitsForTheUpdateToInstallIt(): void {
		$forum = self::forum();
		$core = self::coreVersions($forum);
		$before = self::schema($forum);

		$forum->addModule('Guestbook', '1.0.0');
		$forum->addModule('Autograph', '1.0.0');

		$this->assertStringContainsString(self::OUT_OF_DATE, $forum->request('index.php'), 'the board waits for the update, as for a release');
		$this->assertStringContainsString('value="Start update"', $forum->requestAsGuest('admin/db_update.php'));

		$pages = self::update($forum);
		$this->assertStringStartsWith("Create table guestbook…<br />\nCreate table autographs…<br />\n<script", $pages, 'Autograph after the module it depends on');
		$this->assertStringContainsString('PunBB Database Update completed!', $pages);
		$this->assertDoesNotMatchRegularExpression('/Warning|Notice|Deprecated|Fatal/', $pages);

		$this->assertSame(array(
			array('name' => 'Autograph', 'schema_version' => '1.0.0', 'data_version' => '1.0.0'),
			array('name' => 'Guestbook', 'schema_version' => '1.0.0', 'data_version' => '1.0.0'),
		), self::versions($forum));
		$this->assertSame($core, self::coreVersions($forum));
		$this->assertSame(array('autographs', 'guestbook'), array_keys(array_diff_key(self::schema($forum), $before)));
		$this->assertSame(array(), array_diff_assoc($before, self::schema($forum)), 'no table of the forum\'s own changes');

		$this->assertStringContainsString(self::UP_TO_DATE, $forum->requestAsGuest('admin/db_update.php'));
	}

	#[Depends('testAModuleAddedToARunningBoardWaitsForTheUpdateToInstallIt')]
	public function testItsPluginAndObserverReachTheBoardIndexAfterTheForumsOwn(): void {
		$forum = self::forum();
		$forum->rows('INSERT INTO guestbook (poster) VALUES (\'Rick\'), (\'Morty\')');

		$index = $forum->request('index.php');

		$this->assertStringContainsString('Total number of posts: <strong>3</strong>', $index, 'the welcome post and two entries');
		$this->assertMatchesRegularExpression('#<p id="guestbook">Guestbook entries: 2</p>\s*<p id="autograph">Signed below 2 entries</p>#', $index);
		$this->assertDoesNotMatchRegularExpression('/Warning|Notice|Deprecated|Fatal/', $index);
	}

	#[Depends('testItsPluginAndObserverReachTheBoardIndexAfterTheForumsOwn')]
	public function testAReleaseUnpackedOverItChangesItsTableAndNoOther(): void {
		$forum = self::forum();
		$core = self::coreVersions($forum);
		$before = self::schema($forum);

		$forum->addModule('Guestbook', '1.1.0');
		$this->assertStringContainsString(self::OUT_OF_DATE, $forum->request('index.php'));

		$pages = self::update($forum);
		$this->assertStringStartsWith("Add column guestbook.approved TINYINT(1)…<br />\n<script", $pages);
		$this->assertStringContainsString('Applying Guestbook::approve_entries…', $pages);
		$this->assertStringContainsString('Approved 2 entries', $pages);
		$this->assertStringContainsString('PunBB Database Update completed!', $pages);

		$after = self::schema($forum);
		$this->assertSame(array('guestbook'), array_keys(array_diff_assoc($after, $before)));
		$this->assertSame(array_keys($before), array_keys($after));
		$this->assertStringContainsString('approved', $after['guestbook']);

		$this->assertSame(array(
			array('name' => 'Autograph', 'schema_version' => '1.0.0', 'data_version' => '1.0.0'),
			array('name' => 'Guestbook', 'schema_version' => '1.1.0', 'data_version' => '1.1.0'),
		), self::versions($forum));
		$this->assertSame($core, self::coreVersions($forum));
		$this->assertSame(array(array('approved' => 1), array('approved' => 1)), $forum->rows('SELECT approved FROM guestbook ORDER BY id'));
		$this->assertSame(array(array('name' => 'Guestbook::approve_entries')), $forum->rows('SELECT name FROM data_patches WHERE name LIKE \'Guestbook::%\''));

		$this->assertStringContainsString(self::UP_TO_DATE, $forum->requestAsGuest('admin/db_update.php'), 'a second run has nothing to do');
		$this->assertStringContainsString('<p id="guestbook">Guestbook entries: 2</p>', $forum->request('index.php'));
	}

	/** Uninstalling is deleting its directory: its table, its rows and its record stay, and the module depending on it is skipped. */
	#[Depends('testAReleaseUnpackedOverItChangesItsTableAndNoOther')]
	public function testDeletingItLeavesItsTableAndItsRecordAndSkipsTheModuleDependingOnIt(): void {
		$forum = self::forum();
		$versions = self::versions($forum);
		$schema = self::schema($forum);

		$forum->removeModule('Guestbook');

		$index = $forum->request('index.php');
		$this->assertStringContainsString('Total number of posts: <strong>1</strong>', $index);
		$this->assertStringNotContainsString('id="guestbook"', $index);
		$this->assertStringNotContainsString('id="autograph"', $index);
		$this->assertStringContainsString('PunBB module skipped: Module Autograph depends on Guestbook, which is not registered', (string) file_get_contents(self::$log));

		$this->assertStringContainsString(self::UP_TO_DATE, $forum->requestAsGuest('admin/db_update.php'), 'a module recorded and not registered is not behind');

		// Every module of the forum's own brought up again: the tables of the absent module and of the skipped one are left as they are
		$forum->rows('UPDATE modules SET schema_version=\'0\', data_version=\'0\' WHERE name NOT IN (\'Autograph\', \'Guestbook\')');
		$pages = self::update($forum);
		$this->assertStringContainsString('PunBB Database Update completed!', $pages);

		$this->assertSame($schema, self::schema($forum));
		$this->assertSame(2, count($forum->rows('SELECT id FROM guestbook')));
		$this->assertSame($versions, self::versions($forum));
	}

	#[Depends('testDeletingItLeavesItsTableAndItsRecordAndSkipsTheModuleDependingOnIt')]
	public function testPutBackItResumesFromItsRecord(): void {
		$forum = self::forum();

		$forum->addModule('Guestbook', '1.0.0');
		$forum->addModule('Guestbook', '1.1.0');

		$index = $forum->request('index.php');
		$this->assertStringNotContainsString(self::OUT_OF_DATE, $index);
		$this->assertStringContainsString('Total number of posts: <strong>3</strong>', $index);
		$this->assertMatchesRegularExpression('#<p id="guestbook">Guestbook entries: 2</p>\s*<p id="autograph">Signed below 2 entries</p>#', $index);
		$this->assertStringContainsString(self::UP_TO_DATE, $forum->requestAsGuest('admin/db_update.php'));
	}
}
