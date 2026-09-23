<?php
/**
 * The updater's repositories over an in-memory SQLite database: the options,
 * the rows a 1.2 board keeps in an older shape, the forums resynchronised,
 * and text read and stored a batch at a time.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Database\Sql\QueryException;
use PunBB\Module\Setup\Database\DatabaseInterface;
use PunBB\Module\Setup\Database\DatabaseSettings;
use PunBB\Module\Update\Api\Data\SettingInterface;
use PunBB\Module\Update\Api\Data\TextRowInterface;
use PunBB\Module\Update\Charset\ConversionException;
use PunBB\Module\Update\Model\BoardData;
use PunBB\Module\Update\Model\BoardSettings;
use PunBB\Module\Update\Model\Conversion;
use PunBB\Module\Update\Model\Setting;
use PunBB\Module\Update\Model\TextRow;
use PunBB\Module\Update\Patch\BoardOptions;
use PunBB\Module\Update\Patch\ConvertMisc;
use PunBB\Module\Update\Patch\ConvertRows;

class BoardUpdateTest extends TestCase {
	private SQLite3 $sqlite;

	private Connection $db;

	protected function setUp(): void {
		$this->sqlite = new SQLite3(':memory:');
		$this->db = new Connection(new Sqlite3Driver($this->sqlite), 'pun_');

		foreach (array(
			'CREATE TABLE pun_config (conf_name VARCHAR(255) PRIMARY KEY, conf_value TEXT)',
			'CREATE TABLE pun_groups (g_id INTEGER PRIMARY KEY, g_title VARCHAR(50), g_moderator INTEGER NOT NULL DEFAULT 0, g_mod_rename_users INTEGER NOT NULL DEFAULT 0, g_send_email INTEGER NOT NULL DEFAULT 1, g_email_flood INTEGER NOT NULL DEFAULT 60)',
			'CREATE TABLE pun_users (id INTEGER PRIMARY KEY, group_id INTEGER, username VARCHAR(200), title VARCHAR(50), linkedin VARCHAR(100), avatar INTEGER NOT NULL DEFAULT 0, avatar_width INTEGER NOT NULL DEFAULT 0, avatar_height INTEGER NOT NULL DEFAULT 0)',
			'CREATE TABLE pun_forum_perms (group_id INTEGER, forum_id INTEGER, read_forum INTEGER)',
			'CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80), num_topics INTEGER, num_posts INTEGER, last_post INTEGER, last_post_id INTEGER, last_poster VARCHAR(200))',
			'CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, subject VARCHAR(255), first_post_id INTEGER NOT NULL DEFAULT 0, last_post INTEGER, last_post_id INTEGER, last_poster VARCHAR(200), num_replies INTEGER, moved_to INTEGER, forum_id INTEGER)',
			'CREATE TABLE pun_posts (id INTEGER PRIMARY KEY, poster VARCHAR(200), message TEXT, topic_id INTEGER)',
			'CREATE TABLE pun_extensions (id VARCHAR(150) PRIMARY KEY, version VARCHAR(25))',
			'CREATE TABLE pun_extension_hooks (id VARCHAR(150), extension_id VARCHAR(50))',
			'CREATE TABLE pun_search_cache (id INTEGER, ident VARCHAR(200))',
			'CREATE TABLE pun_online (user_id INTEGER, ident VARCHAR(200))',
		) as $statement)
			$this->db->execute($statement);
	}

	/** @return list<list<mixed>> */
	private function rows(string $sql): array {
		$result = $this->sqlite->query($sql);
		$rows = array();
		while (($row = $result->fetchArray(SQLITE3_NUM)) !== false)
			$rows[] = $row;

		return $rows;
	}

	/** A connection whose character set is not the test's concern. */
	private static function database(): DatabaseInterface {
		return new class implements DatabaseInterface {
			public function open(DatabaseSettings $settings): void {}

			public function openUnencoded(DatabaseSettings $settings): void {}

			public function serverVersion(): string { return ''; }

			public function supportsInnodb(): bool { return false; }

			public function setNames(string $charset): void {}

			public function startTransaction(): void {}

			public function endTransaction(): void {}

			public function rollBack(): void {}

			public function close(): void {}
		};
	}

	/** A 1.2 board converting from ISO-8859-1, whose writes to $table's row $id fail until the trigger is dropped. */
	private function interruptedAt(string $table, int $id): BoardSettings {
		$this->db->execute('INSERT INTO pun_config (conf_name, conf_value) VALUES (\'o_cur_version\', \'1.2.15\'), (\'update:charset\', \'ISO-8859-1\')');
		$this->db->execute('CREATE TRIGGER lost BEFORE UPDATE ON pun_'.$table.' WHEN NEW.id = '.$id.' BEGIN SELECT RAISE(ABORT, \'Lost connection to the server\'); END');

		return new BoardSettings($this->db);
	}

	private function assertConnectionLost(callable $run): void {
		try {
			$run();
			$this->fail('the lost connection stops the patch');
		}
		catch (QueryException $e) {
			$this->assertStringContainsString('Lost connection to the server', $e->getMessage());
		}

		$this->db->execute('DROP TRIGGER lost');
	}

	/** @return list<string> the update's progress options the board holds */
	private function progress(): array {
		return array_map(static fn (array $row): string => (string) $row[0], $this->rows('SELECT conf_name FROM pun_config WHERE conf_name LIKE \'update:%\' ORDER BY conf_name'));
	}

	public function testARowsConversionInterruptedPartWayDecodesEachRowOnce(): void {
		// Row 3 holds 'Jörg' as ISO-8859-1 bytes
		$this->db->execute('INSERT INTO pun_users (id, username) VALUES (2, \'&amp;amp;\'), (3, CAST(X\'4AF67267\' AS TEXT)), (4, \'&amp;amp;\')');
		$settings = $this->interruptedAt('users', 3);
		$patch = new ConvertRows($settings, new Conversion($this->db), self::database(), 'users', array('username'), array(), 'user');

		$this->assertConnectionLost(static fn (): mixed => $patch->apply(0));
		$this->assertSame(array('update:charset', 'update:converted'), $this->progress(), 'the cursor is inserted, not updated into nothing');
		$this->assertSame(array(array('&amp;')), $this->rows('SELECT username FROM pun_users WHERE id=2'), 'SQLite keeps the row stored before the failure');

		$this->assertNull($patch->apply(0)->next);

		$this->assertSame(array(array(2, '&amp;'), array(3, 'Jörg'), array(4, '&amp;')), $this->rows('SELECT id, username FROM pun_users ORDER BY id'), 'each row is decoded once');
		$this->assertSame(array(array('users:302')), $this->rows('SELECT conf_value FROM pun_config WHERE conf_name=\'update:converted\''));

		$settings->remove(...BoardOptions::progress($settings));
		$this->assertSame(array(), $this->progress());
	}

	public function testAMiscConversionInterruptedPartWayDecodesEachValueOnce(): void {
		foreach (array(
			'ALTER TABLE pun_groups ADD g_user_title VARCHAR(50)',
			'ALTER TABLE pun_forums ADD forum_desc TEXT',
			'ALTER TABLE pun_forums ADD moderators TEXT',
			'CREATE TABLE pun_categories (id INTEGER PRIMARY KEY, cat_name VARCHAR(80))',
			'CREATE TABLE pun_ranks (id INTEGER PRIMARY KEY, rank VARCHAR(50))',
			'CREATE TABLE pun_censoring (id INTEGER PRIMARY KEY, search_for VARCHAR(60), replace_with VARCHAR(60))',
			'INSERT INTO pun_config (conf_name, conf_value) VALUES (\'o_board_title\', \'&amp;amp;\')',
			'INSERT INTO pun_categories (id, cat_name) VALUES (1, \'&amp;amp;\'), (2, \'&amp;amp;\')',
		) as $statement)
			$this->db->execute($statement);

		$settings = $this->interruptedAt('categories', 2);
		$patch = new ConvertMisc($settings, new Conversion($this->db), self::database());

		$this->assertConnectionLost(static fn (): mixed => $patch->apply(0));
		$this->assertSame(array('update:charset', 'update:converted_misc', 'update:converted_misc_1', 'update:converted_misc_written'), $this->progress(), 'the journal is inserted, not updated into nothing');

		$patch->apply(0);

		$this->assertSame(array(array('&amp;')), $this->rows('SELECT conf_value FROM pun_config WHERE conf_name=\'o_board_title\''), 'a stored option is decoded once');
		$this->assertSame(array(array(1, '&amp;'), array(2, '&amp;')), $this->rows('SELECT id, cat_name FROM pun_categories ORDER BY id'), 'each row is decoded once');

		$settings->remove(...BoardOptions::progress($settings));
		$this->assertSame(array(), $this->progress());
	}

	public function testTheOptionsAreReadAddedChangedRenamedAndRemoved(): void {
		$settings = new BoardSettings($this->db);
		$this->assertNull($settings->version());

		$settings->add(new Setting('o_cur_version', '1.2.15'), new Setting('o_server_timezone', '2'), new Setting('o_default_user_group', '4'), new Setting('p_mod_ban_users', null));
		$settings->update(new Setting('o_cur_version', '1.2.16'));
		$settings->replace(new Setting('o_default_user_group', '3'), '4');
		$settings->replace(new Setting('o_cur_version', 'never'), '1.0');
		$settings->rename('o_server_timezone', 'o_default_timezone');
		$settings->remove('p_mod_ban_users');

		$this->assertSame('1.2.16', $settings->version());
		$this->assertSame(array('o_cur_version=1.2.16', 'o_default_timezone=2', 'o_default_user_group=3'),
			array_map(static fn (SettingInterface $setting): string => $setting->name().'='.$setting->value(), $settings->all()));
	}

	public function testTheModeratorsGroupMovesToFourWithItsMembersAndPermissions(): void {
		$this->db->execute('INSERT INTO pun_groups (g_id, g_title) VALUES (1, \'Administrators\'), (2, \'Moderators\'), (3, \'Guest\'), (4, \'Members\')');
		$this->db->execute('INSERT INTO pun_users (id, group_id) VALUES (1, 3), (2, 1), (3, 2), (4, 4)');
		$this->db->execute('INSERT INTO pun_forum_perms (group_id, forum_id, read_forum) VALUES (2, 1, 1), (3, 1, 0)');

		$data = new BoardData($this->db);
		$this->assertFalse($data->hasModeratorGroup(), 'a 1.2 board has no moderating group');

		// Each step twice, as a run interrupted before recording it takes it again
		$spare = $data->spareGroupId();
		for ($step = 0; $data->reorderGroups($spare, $step); ++$step)
			$data->reorderGroups($spare, $step);

		$this->assertSame(13, $step);
		$this->assertTrue($data->hasModeratorGroup(), 'so a rerun leaves the groups where they are');

		$this->assertSame(array(array(1, 'Administrators', 0), array(2, 'Guest', 0), array(3, 'Members', 0), array(4, 'Moderators', 1)), $this->rows('SELECT g_id, g_title, g_moderator FROM pun_groups ORDER BY g_id'));
		$this->assertSame(array(array(1, 2), array(2, 1), array(3, 4), array(4, 3)), $this->rows('SELECT id, group_id FROM pun_users ORDER BY id'));
		$this->assertSame(array(array(2, 0), array(4, 1)), $this->rows('SELECT group_id, read_forum FROM pun_forum_perms ORDER BY group_id'));
	}

	public function testModeratorsAreGrantedOnlyAModeratorPermission(): void {
		$this->db->execute('INSERT INTO pun_groups (g_id, g_moderator) VALUES (1, 0), (4, 1)');
		$data = new BoardData($this->db);

		$data->grantModerators('g_mod_rename_users', 1);
		$data->limitGroupMail();

		$this->assertSame(array(array(1, 0, 1, 0), array(4, 1, 1, 0)), $this->rows('SELECT g_id, g_mod_rename_users, g_send_email, g_email_flood FROM pun_groups ORDER BY g_id'));

		$this->expectException(ConversionException::class);
		$data->grantModerators('g_title', 1);
	}

	public function testTheRowsOfAnOlderShapeAreRewritten(): void {
		$this->db->execute('INSERT INTO pun_users (id, group_id, linkedin) VALUES (2, 32000, \'linkedin.com/in/a\'), (3, 3, \'HTTPS://linkedin.com/in/b\'), (4, 3, \'\')');
		$this->db->execute('INSERT INTO pun_posts (id, poster, message, topic_id) VALUES (5, \'a\', \'x\', 1), (3, \'b\', NULL, 1), (9, \'c\', \'z\', 2)');
		$this->db->execute('INSERT INTO pun_topics (id, forum_id) VALUES (1, 1), (2, 1)');
		$this->db->execute('INSERT INTO pun_extensions (id, version) VALUES (\'hotfix_1_2\', \'1.4\'), (\'hotfix_now\', \'1.5.1\'), (\'other\', \'1.0\')');
		$this->db->execute('INSERT INTO pun_extension_hooks (id, extension_id) VALUES (\'hd_head\', \'hotfix_1_2\'), (\'hd_head\', \'other\')');

		$data = new BoardData($this->db);
		$data->moveUnverifiedUsers();
		$data->schemeLinkedinAddresses();
		$data->recordFirstPosts();
		$data->storeAvatar(3, 3, 60, 40);

		$this->assertSame(array('hotfix_1_2'), $data->supersededHotfixes('1.5.1'));
		$data->removeExtension('hotfix_1_2');

		$this->assertSame(array(array(2, 0, 'http://linkedin.com/in/a', 0), array(3, 3, 'HTTPS://linkedin.com/in/b', 3), array(4, 3, '', 0)), $this->rows('SELECT id, group_id, linkedin, avatar FROM pun_users ORDER BY id'));
		$this->assertSame(array(array(1, 3), array(2, 9)), $this->rows('SELECT id, first_post_id FROM pun_topics ORDER BY id'));
		$this->assertSame(array(array('hotfix_now'), array('other')), $this->rows('SELECT id FROM pun_extensions ORDER BY id'));
		$this->assertSame(array(array('other')), $this->rows('SELECT extension_id FROM pun_extension_hooks'));
		$this->assertSame(array(array(60, 40)), $this->rows('SELECT avatar_width, avatar_height FROM pun_users WHERE id=3'));

		$range = $data->postRange();
		$this->assertSame(array(3, 9, 3), array($range->lowest(), $range->highest(), $range->count()));
	}

	public function testAForumIsSynchronisedFromItsTopics(): void {
		$this->db->execute('INSERT INTO pun_forums (id, forum_name) VALUES (1, \'One\'), (2, \'Empty\')');
		$this->db->execute('INSERT INTO pun_topics (id, subject, last_post, last_post_id, last_poster, num_replies, moved_to, forum_id) VALUES (1, \'a\', 100, 11, \'ann\', 2, NULL, 1), (2, \'b\', 300, 22, NULL, 0, NULL, 1), (3, \'moved\', 900, 33, \'x\', 0, 5, 1)');
		$this->db->execute('INSERT INTO pun_posts (id, poster, message, topic_id) VALUES (11, \'ann\', \'hi\', 1)');
		$this->db->execute('INSERT INTO pun_search_cache VALUES (1, \'x\')');
		$this->db->execute('INSERT INTO pun_online VALUES (1, \'x\')');

		$data = new BoardData($this->db);
		$this->assertSame(array(1, 2), $data->forumIds());
		$this->assertSame('hiannaOne', $data->postText(1));
		$this->assertNull($data->postText(12));

		$data->syncForum(1);
		$data->syncForum(2);
		$data->emptySearchCache();
		$data->emptyOnline();

		$this->assertSame(array(array(1, 3, 5, 300, 22, ''), array(2, 0, 0, null, null, null)), $this->rows('SELECT id, num_topics, num_posts, last_post, last_post_id, last_poster FROM pun_forums ORDER BY id'));
		$this->assertSame(array(array(0), array(0)), array($this->rows('SELECT COUNT(*) FROM pun_search_cache')[0], $this->rows('SELECT COUNT(*) FROM pun_online')[0]));
	}

	public function testTextIsReadAndStoredABatchAtATime(): void {
		$this->db->execute('INSERT INTO pun_users (id, username, title) VALUES (2, \'a\', NULL), (5, \'b\', \'t\'), (9, \'c\', NULL)');
		$this->db->execute('INSERT INTO pun_groups (g_id, g_title) VALUES (4, \'Mods\')');
		$conversion = new Conversion($this->db);

		$this->assertSame(2, $conversion->firstId('users'));
		$this->assertSame(9, $conversion->nextId('users', 6));
		$this->assertNull($conversion->nextId('users', 10));
		$this->assertNull($conversion->firstId('posts'));

		$batch = $conversion->rows('users', 'id', array('username', 'title'), 2, 9);
		$this->assertSame(array('2 a -', '5 b t'), array_map(static fn (TextRowInterface $row): string => $row->id().' '.$row->value('username').' '.($row->value('title') ?? '-'), $batch));
		$this->assertCount(3, $conversion->rows('users', 'id', array('username')));

		$conversion->store('users', 'id', new TextRow(5, array('username' => 'B', 'title' => null)));
		$conversion->store('groups', 'g_id', (new TextRow(4, array('g_title' => 'x')))->with('g_title', 'Moderators'));

		$this->assertSame(array(array(5, 'B', null)), $this->rows('SELECT id, username, title FROM pun_users WHERE id=5'));
		$this->assertSame(array(array('Moderators')), $this->rows('SELECT g_title FROM pun_groups'));
	}
}
