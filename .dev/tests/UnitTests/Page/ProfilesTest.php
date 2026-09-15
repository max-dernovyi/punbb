<?php
/**
 * The profile's repository over an in-memory SQLite database with the forum's
 * tables: a member with their group, passwords and addresses, a group and the
 * forums' moderators, an avatar, a section of details, and a new name put
 * everywhere the old one was written.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\DatabaseException;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Profile\Api\Data\ModeratorInterface;
use PunBB\Module\Profile\Model\Avatar;
use PunBB\Module\Profile\Model\Details;
use PunBB\Module\Profile\Model\EmailActivation;
use PunBB\Module\Profile\Model\EmailChange;
use PunBB\Module\Profile\Model\ForumModerators;
use PunBB\Module\Profile\Model\Moderator;
use PunBB\Module\Profile\Model\Password;
use PunBB\Module\Profile\Model\Profiles;
use PunBB\Module\Profile\Model\Rename;

class ProfilesTest extends TestCase {
	private Connection $db;

	private Profiles $profiles;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');
		$this->db->execute('CREATE TABLE pun_groups (g_id INTEGER PRIMARY KEY, g_title VARCHAR(50) NOT NULL, g_user_title VARCHAR(50), g_moderator INTEGER NOT NULL DEFAULT 0)');
		$this->db->execute('CREATE TABLE pun_users (id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL DEFAULT 3, username VARCHAR(200) NOT NULL, password VARCHAR(255) NOT NULL DEFAULT \'\', salt VARCHAR(12), email VARCHAR(80) NOT NULL DEFAULT \'\', title VARCHAR(50), realname VARCHAR(40), "timezone" FLOAT NOT NULL DEFAULT 0, disp_topics INTEGER, num_posts INTEGER NOT NULL DEFAULT 0, last_post INTEGER, avatar INTEGER NOT NULL DEFAULT 0, avatar_width INTEGER NOT NULL DEFAULT 0, avatar_height INTEGER NOT NULL DEFAULT 0, activate_string VARCHAR(80), activate_key VARCHAR(8), probe VARCHAR(10))');
		$this->db->execute('CREATE TABLE pun_categories (id INTEGER PRIMARY KEY, cat_name VARCHAR(80) NOT NULL, disp_position INTEGER NOT NULL DEFAULT 0)');
		$this->db->execute('CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80) NOT NULL, moderators TEXT, last_poster VARCHAR(200), redirect_url VARCHAR(100), disp_position INTEGER NOT NULL DEFAULT 0, cat_id INTEGER NOT NULL)');
		$this->db->execute('CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, poster VARCHAR(200) NOT NULL, last_poster VARCHAR(200))');
		$this->db->execute('CREATE TABLE pun_posts (id INTEGER PRIMARY KEY, poster VARCHAR(200) NOT NULL, poster_id INTEGER NOT NULL, edited_by VARCHAR(200))');
		$this->db->execute('CREATE TABLE pun_online (user_id INTEGER NOT NULL, ident VARCHAR(200) NOT NULL)');

		$this->db->execute('INSERT INTO pun_groups (g_id, g_title, g_user_title, g_moderator) VALUES (1, ?, ?, 0), (2, ?, NULL, 0), (3, ?, NULL, 0), (4, ?, ?, 1)', 'Administrators', 'Administrator', 'Guests', 'Members', 'Moderators', 'Moderator');
		$this->db->execute('INSERT INTO pun_users (id, group_id, username, password, salt, email, title, "timezone", disp_topics, num_posts, activate_string, activate_key) VALUES (2, 1, ?, ?, NULL, ?, NULL, 5.5, NULL, 12, NULL, NULL), (3, 4, ?, ?, ?, ?, ?, -3, 30, 4, ?, ?), (4, 9, ?, \'\', NULL, ?, NULL, 0, NULL, 0, NULL, NULL)',
			'admin', 'hash', 'admin@example.com', 'mod', 'old', 'salt', 'mod@example.com', 'Chief', 'new@example.com', 'KEY12345', 'orphan', 'orphan@example.com');
		$this->db->execute('INSERT INTO pun_categories (id, cat_name, disp_position) VALUES (1, ?, 2), (2, ?, 1)', 'Second', 'First');
		$this->db->execute('INSERT INTO pun_forums (id, forum_name, moderators, last_poster, redirect_url, disp_position, cat_id) VALUES (1, ?, ?, ?, NULL, 1, 1), (2, ?, NULL, ?, NULL, 0, 2), (3, ?, NULL, NULL, ?, 2, 2)',
			'News', serialize(array('mod' => 3, 'zed' => 9)), 'mod', 'Chat', 'admin', 'Away', 'http://example.com');
		$this->db->execute('INSERT INTO pun_topics (id, poster, last_poster) VALUES (1, ?, ?), (2, ?, ?)', 'mod', 'admin', 'admin', 'mod');
		$this->db->execute('INSERT INTO pun_posts (id, poster, poster_id, edited_by) VALUES (1, ?, 3, NULL), (2, ?, 2, ?)', 'mod', 'admin', 'mod');
		$this->db->execute('INSERT INTO pun_online (user_id, ident) VALUES (3, ?), (1, ?)', 'mod', '192.0.2.1');

		$this->profiles = new Profiles($this->db);
	}

	/** @return list<string> */
	private function rows(string $sql): array {
		return array_map(static fn ($row): string => implode('|', array_map(static fn (mixed $value): string => var_export($value, true), $row->values())), $this->db->select($sql));
	}

	/**
	 * @param list<ModeratorInterface> $moderators
	 * @return list<string>
	 */
	private static function named(array $moderators): array {
		return array_map(static fn (ModeratorInterface $moderator): string => $moderator->username().' '.$moderator->userId(), $moderators);
	}

	public function testAMemberIsReadWithTheirGroup(): void {
		$mod = $this->profiles->user(3);

		$this->assertNotNull($mod);
		$this->assertSame(array(3, 'mod', 'Chief', 'mod@example.com', '-3', 30, 4, null, 'old', 'salt', 'KEY12345', 4, 'Moderator', true, false),
			array($mod->id(), $mod->username(), $mod->title(), $mod->email(), $mod->timezone(), $mod->topicsPerPage(), $mod->posts(), $mod->lastPost(), $mod->passwordHash(), $mod->salt(), $mod->activateKey(), $mod->groupId(), $mod->groupTitle(), $mod->moderates(), $mod->isAdministrator()));

		$admin = $this->profiles->user(2);
		$this->assertSame(array('5.5', null, '', '', true, false), array($admin?->timezone(), $admin?->topicsPerPage(), $admin?->title(), $admin?->activateKey(), $admin?->isAdministrator(), $admin?->moderates()));

		$orphan = $this->profiles->user(4);
		$this->assertSame(array(null, null, false), array($orphan?->groupId(), $orphan?->groupTitle(), $orphan?->isAdministrator()), 'a member whose group is gone has none');

		$this->assertNull($this->profiles->user(99));
	}

	public function testAnAvatarKeepsTheMemberAsTheyWere(): void {
		$mod = $this->profiles->user(3)?->withAvatar(2, 60, 40);

		$this->assertSame(array(2, 60, 40, 'mod', 'Chief'), array($mod?->avatarType(), $mod?->avatarWidth(), $mod?->avatarHeight(), $mod?->username(), $mod?->title()));
	}

	public function testPasswordsAreStoredAndAResetDropsItsKey(): void {
		$this->profiles->changePassword(new Password(2, 'changed'));
		$this->profiles->resetPassword(new Password(3, 'reset'));

		$this->assertSame(array("'changed'|NULL", "'reset'|NULL"), $this->rows('SELECT password, activate_key FROM pun_users WHERE id IN (2, 3) ORDER BY id'));
	}

	public function testAnAddressIsChangedRequestedAndConfirmed(): void {
		$this->assertSame(array('admin'), $this->profiles->usernamesWithEmail('admin@example.com'));
		$this->assertSame(array(), $this->profiles->usernamesWithEmail('nobody@example.com'));

		$this->profiles->changeEmail(new EmailChange(2, 'boss@example.com'));
		$this->profiles->requestEmailChange(new EmailActivation(4, 'found@example.com', 'ORPHANKY'));
		$this->profiles->confirmEmail(3);

		$this->assertSame(array("'boss@example.com'|NULL|NULL", "'new@example.com'|NULL|NULL", "'orphan@example.com'|'found@example.com'|'ORPHANKY'"),
			$this->rows('SELECT email, activate_string, activate_key FROM pun_users WHERE id IN (2, 3, 4) ORDER BY id'));
	}

	public function testAMemberMovesIntoAGroupThatModeratesOrNot(): void {
		$this->profiles->moveToGroup(3, 2, 4);

		$this->assertSame(array('3', '4', '3'), array_map(static fn (string $row): string => trim($row, "'"), $this->rows('SELECT group_id FROM pun_users ORDER BY id')));
		$this->assertTrue($this->profiles->groupModerates(4));
		$this->assertFalse($this->profiles->groupModerates(3));
		$this->assertFalse($this->profiles->groupModerates(99));
	}

	public function testTheForumsModeratorsAreReadAndStored(): void {
		$forums = $this->profiles->forumModerators();

		$this->assertSame(array(1, 2, 3), array_map(static fn ($forum): int => $forum->forumId(), $forums));
		$this->assertSame(array('mod 3', 'zed 9'), self::named($forums[0]->moderators()));
		$this->assertSame(array(), $forums[1]->moderators());

		$this->profiles->storeModerators(new ForumModerators(1, array()), new ForumModerators(2, array(new Moderator(3, 'mod'))));

		$this->assertSame(array('NULL', var_export(serialize(array('mod' => 3)), true)), $this->rows('SELECT moderators FROM pun_forums WHERE id IN (1, 2) ORDER BY id'));
	}

	public function testTheListsTheAdministrationShows(): void {
		$this->assertSame(array('1 Administrators', '3 Members', '4 Moderators'), array_map(static fn ($group): string => $group->id().' '.$group->title(), $this->profiles->groups()));

		$forums = $this->profiles->moderatableForums();
		$this->assertSame(array('2 First 2 Chat', '1 Second 1 News'), array_map(static fn ($forum): string => $forum->categoryId().' '.$forum->categoryName().' '.$forum->forumId().' '.$forum->forumName(), $forums), 'a redirect is not moderated, and the categories come in their order');
		$this->assertSame(array('mod 3', 'zed 9'), self::named($forums[1]->moderators()));
	}

	public function testAnAvatarAndASectionAreStored(): void {
		$this->profiles->storeAvatar(new Avatar(3, 3, 60, 40));
		$this->profiles->updateDetails(new Details(3, array('realname' => 'Mod "M"', 'title' => null, 'timezone' => '5.75', 'disp_topics' => '10')));

		$this->assertSame(array("3|60|40|'Mod \"M\"'|NULL|5.75|10"), $this->rows('SELECT avatar, avatar_width, avatar_height, realname, title, "timezone", disp_topics FROM pun_users WHERE id=3'));
	}

	public function testASectionNamesOnlyColumns(): void {
		$this->expectException(DatabaseException::class);

		$this->profiles->updateDetails(new Details(3, array('probe=1, group_id' => '1')));
	}

	public function testANewNameIsPutEverywhereTheOldOneWasWritten(): void {
		$rename = new Rename(3, 'mod', 'moderator');

		$this->profiles->renamePosts($rename);
		$this->profiles->renameTopics($rename);
		$this->profiles->renameTopicLastPosters($rename);
		$this->profiles->renameForumLastPosters($rename);
		$this->profiles->renameOnline($rename);
		$this->profiles->renameEditors($rename);

		$this->assertSame(array("'moderator'|NULL", "'admin'|'moderator'"), $this->rows('SELECT poster, edited_by FROM pun_posts ORDER BY id'));
		$this->assertSame(array("'moderator'|'admin'", "'admin'|'moderator'"), $this->rows('SELECT poster, last_poster FROM pun_topics ORDER BY id'));
		$this->assertSame(array("'moderator'", "'admin'", 'NULL'), $this->rows('SELECT last_poster FROM pun_forums ORDER BY id'));
		$this->assertSame(array("'moderator'", "'192.0.2.1'"), $this->rows('SELECT ident FROM pun_online ORDER BY user_id DESC'));
	}
}
