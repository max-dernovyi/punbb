<?php
/**
 * The installer's repository over an in-memory SQLite database with the
 * forum's tables: whether a board is installed, the preset groups and
 * accounts with their ids, the settings, the welcome post filed in a category,
 * a forum and a topic of its own, the ranks and a shipped extension.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Install\Model\Administrator;
use PunBB\Module\Install\Model\BoardInstallation;
use PunBB\Module\Install\Model\BundledExtension;
use PunBB\Module\Install\Model\BundledHook;
use PunBB\Module\Install\Model\Rank;
use PunBB\Module\Install\Model\Setting;
use PunBB\Module\Install\Model\Welcome;

class BoardInstallationTest extends TestCase {
	private SQLite3 $sqlite;

	private BoardInstallation $board;

	protected function setUp(): void {
		$this->sqlite = new SQLite3(':memory:');
		$db = new Connection(new Sqlite3Driver($this->sqlite), 'pun_');

		$db->execute('CREATE TABLE pun_groups (g_id INTEGER PRIMARY KEY, g_title VARCHAR(50), g_user_title VARCHAR(50), g_moderator INTEGER, g_mod_edit_users INTEGER, g_mod_rename_users INTEGER, g_mod_change_passwords INTEGER, g_mod_ban_users INTEGER, g_read_board INTEGER, g_view_users INTEGER, g_post_replies INTEGER, g_post_topics INTEGER, g_edit_posts INTEGER, g_delete_posts INTEGER, g_delete_topics INTEGER, g_set_title INTEGER, g_search INTEGER, g_search_users INTEGER, g_send_email INTEGER, g_post_flood INTEGER, g_search_flood INTEGER, g_email_flood INTEGER)');
		$db->execute('CREATE TABLE pun_users (id INTEGER PRIMARY KEY, group_id INTEGER, username VARCHAR(200), password VARCHAR(255), salt VARCHAR(12), email VARCHAR(80), language VARCHAR(25), num_posts INTEGER, last_post INTEGER, registered INTEGER, registration_ip VARCHAR(39), last_visit INTEGER)');
		$db->execute('CREATE TABLE pun_config (conf_name VARCHAR(255) PRIMARY KEY, conf_value TEXT)');
		$db->execute('CREATE TABLE pun_categories (id INTEGER PRIMARY KEY, cat_name VARCHAR(80), disp_position INTEGER)');
		$db->execute('CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80), forum_desc TEXT, num_topics INTEGER, num_posts INTEGER, last_post INTEGER, last_post_id INTEGER, last_poster VARCHAR(200), disp_position INTEGER, cat_id INTEGER)');
		$db->execute('CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, poster VARCHAR(200), subject VARCHAR(255), posted INTEGER, first_post_id INTEGER, last_post INTEGER, last_post_id INTEGER, last_poster VARCHAR(200), forum_id INTEGER)');
		$db->execute('CREATE TABLE pun_posts (id INTEGER PRIMARY KEY, poster VARCHAR(200), poster_id INTEGER, poster_ip VARCHAR(39), message TEXT, posted INTEGER, topic_id INTEGER)');
		$db->execute('CREATE TABLE pun_ranks (id INTEGER PRIMARY KEY, "rank" VARCHAR(50), min_posts INTEGER)');
		$db->execute('CREATE TABLE pun_extensions (id VARCHAR(150) PRIMARY KEY, title VARCHAR(255), version VARCHAR(25), description TEXT, author VARCHAR(50), uninstall TEXT, uninstall_note TEXT, disabled INTEGER NOT NULL DEFAULT 0, dependencies VARCHAR(255))');
		$db->execute('CREATE TABLE pun_extension_hooks (id VARCHAR(150), extension_id VARCHAR(50), code TEXT, installed INTEGER, priority INTEGER, PRIMARY KEY (id, extension_id))');

		$this->board = new BoardInstallation($db);
	}

	/** @return list<list<mixed>> */
	private function rows(string $sql): array {
		$result = $this->sqlite->query($sql);
		$rows = array();
		while (($row = $result->fetchArray(SQLITE3_NUM)) !== false)
			$rows[] = $row;

		return $rows;
	}

	public function testABoardIsInstalledOnceItsGuestIs(): void {
		$this->assertFalse($this->board->isInstalled());

		$this->board->addGuest();

		$this->assertTrue($this->board->isInstalled());
		$this->assertSame(array(array(1, 2, 'Guest', 'Guest', 'Guest')), $this->rows('SELECT id, group_id, username, password, email FROM pun_users'));
	}

	public function testThePresetGroupsTakeTheirIds(): void {
		$this->board->addGroups();

		$this->assertSame(array(
			array(1, 'Administrators', 'Administrator', 0, 0, 0),
			array(2, 'Guest', null, 0, 60, 0),
			array(3, 'Members', null, 0, 60, 60),
			array(4, 'Moderators', 'Moderator', 1, 0, 0),
		), $this->rows('SELECT g_id, g_title, g_user_title, g_moderator, g_post_flood, g_email_flood FROM pun_groups ORDER BY g_id'));
	}

	public function testTheAdministratorPostsTheWelcomeInAForumOfItsOwn(): void {
		$this->board->addGuest();
		$id = $this->board->addAdministrator(new Administrator('admin', 'hash', 'salt', 'admin@example.com', 'English', 1000));

		$postId = $this->board->addWelcome(new Welcome('Category', 'Forum', 'A forum', 'Hello', 'Welcome!', 'admin', $id, 1000));

		$this->assertSame(2, $id);
		$this->assertSame(1, $postId);
		$this->assertSame(array(array(2, 1, 'admin', 'hash', 'salt', 'English', 1, 1000, '127.0.0.1')), $this->rows('SELECT id, group_id, username, password, salt, language, num_posts, registered, registration_ip FROM pun_users WHERE id=2'));
		$this->assertSame(array(array(1, 'Category', 1)), $this->rows('SELECT id, cat_name, disp_position FROM pun_categories'));
		$this->assertSame(array(array(1, 'Forum', 'A forum', 1, 1, 1000, 1, 'admin', 1)), $this->rows('SELECT id, forum_name, forum_desc, num_topics, num_posts, last_post, last_post_id, last_poster, cat_id FROM pun_forums'));
		$this->assertSame(array(array(1, 'admin', 'Hello', 1, 1, 1)), $this->rows('SELECT id, poster, subject, first_post_id, last_post_id, forum_id FROM pun_topics'));
		$this->assertSame(array(array(1, 'admin', 2, '127.0.0.1', 'Welcome!', 1)), $this->rows('SELECT id, poster, poster_id, poster_ip, message, topic_id FROM pun_posts'));
	}

	public function testSettingsRanksAndAnExtensionAreStored(): void {
		$this->board->addSettings(new Setting('o_board_title', 'It\'s a "board"'), new Setting('o_smtp_host', null));
		$this->board->addRanks(new Rank('New member', 0), new Rank('Member', 10));
		$this->board->addExtension(new BundledExtension('pun_repository', 'Repository', '1.3', 'Downloads', 'PunBB', array(new BundledHook('hd_head', 'echo 1;', 4, 1000), new BundledHook('ft_end', 'echo 2;', 5, 1000))));

		$this->assertSame(array(array('o_board_title', 'It\'s a "board"'), array('o_smtp_host', null)), $this->rows('SELECT conf_name, conf_value FROM pun_config ORDER BY conf_name'));
		$this->assertSame(array(array('New member', 0), array('Member', 10)), $this->rows('SELECT "rank", min_posts FROM pun_ranks ORDER BY id'));
		$this->assertSame(array(array('pun_repository', 'Repository', '1.3', null, null, 0, '||')), $this->rows('SELECT id, title, version, uninstall, uninstall_note, disabled, dependencies FROM pun_extensions'));
		$this->assertSame(array(array('ft_end', 'pun_repository', 'echo 2;', 1000, 5), array('hd_head', 'pun_repository', 'echo 1;', 1000, 4)), $this->rows('SELECT id, extension_id, code, installed, priority FROM pun_extension_hooks ORDER BY id'));
	}
}
