<?php
/**
 * The groups page's repository over an in-memory SQLite database with the
 * forum's tables: the lists of groups, a group with every permission, adding
 * and updating one with the forums' permissions of the group it is based on,
 * the default group, and removing a group with its members moved.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Groups\Api\Data\ListedGroupInterface;
use PunBB\Module\Groups\Model\ForumPermissions;
use PunBB\Module\Groups\Model\Group;
use PunBB\Module\Groups\Model\Groups;
use PunBB\Module\Site\Visitor\GroupPermission;

class GroupsTest extends TestCase {
	private Connection $db;

	private Groups $groups;

	protected function setUp(): void {
		$flags = '';
		foreach (GroupPermission::cases() as $permission)
			$flags .= ', '.$permission->value.' INTEGER NOT NULL DEFAULT 0';

		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');
		$this->db->execute('CREATE TABLE pun_groups (g_id INTEGER PRIMARY KEY, g_title VARCHAR(50) NOT NULL DEFAULT \'\', g_user_title VARCHAR(50)'.$flags.', g_post_flood INTEGER NOT NULL DEFAULT 30, g_search_flood INTEGER NOT NULL DEFAULT 30, g_email_flood INTEGER NOT NULL DEFAULT 60, g_probe INTEGER NOT NULL DEFAULT 7)');
		$this->db->execute('CREATE TABLE pun_forum_perms (group_id INTEGER NOT NULL, forum_id INTEGER NOT NULL, read_forum INTEGER NOT NULL DEFAULT 1, post_replies INTEGER NOT NULL DEFAULT 1, post_topics INTEGER NOT NULL DEFAULT 1, PRIMARY KEY (group_id, forum_id))');
		$this->db->execute('CREATE TABLE pun_users (id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL, username VARCHAR(200) NOT NULL)');
		$this->db->execute('CREATE TABLE pun_config (conf_name VARCHAR(255) NOT NULL PRIMARY KEY, conf_value TEXT)');

		$this->db->execute('INSERT INTO pun_groups (g_id, g_title, g_user_title, g_moderator, g_read_board, g_post_replies, g_edit_posts, g_post_flood) VALUES (1, ?, ?, 0, 1, 1, 1, 0), (2, ?, NULL, 0, 1, 0, 0, 60), (3, ?, NULL, 0, 1, 1, 1, 30), (4, ?, ?, 1, 1, 1, 1, 0), (5, ?, NULL, 0, 0, 0, 0, 30)',
			'Administrators', 'Administrator', 'Guest', 'Members', 'Moderators', 'Moderator', 'Banned');
		$this->db->execute('INSERT INTO pun_forum_perms (group_id, forum_id, read_forum, post_replies, post_topics) VALUES (3, 1, 1, 0, 0), (3, 2, 0, 0, 0), (4, 1, 1, 1, 1)');
		$this->db->execute('INSERT INTO pun_users (id, group_id, username) VALUES (1, 2, ?), (2, 1, ?), (3, 3, ?), (4, 3, ?), (5, 4, ?)', 'Guest', 'admin', 'one', 'two', 'mod');
		$this->db->execute('INSERT INTO pun_config (conf_name, conf_value) VALUES (?, ?), (?, ?)', 'o_default_user_group', '3', 'o_board_title', 'Board');

		$this->groups = new Groups($this->db);
	}

	/**
	 * @param list<ListedGroupInterface> $groups
	 * @return list<string>
	 */
	private static function listed(array $groups): array {
		return array_map(static fn (ListedGroupInterface $group): string => $group->id().' '.$group->title(), $groups);
	}

	public function testTheListsAreByTitle(): void {
		$this->assertSame(array('1 Administrators', '5 Banned', '2 Guest', '3 Members', '4 Moderators'), self::listed($this->groups->all()));
		$this->assertSame(array('5 Banned', '3 Members', '4 Moderators'), self::listed($this->groups->baseGroups()));
		$this->assertSame(array('5 Banned', '3 Members'), self::listed($this->groups->defaultCandidates()));
		$this->assertSame(array('1 Administrators', '5 Banned', '3 Members'), self::listed($this->groups->moveTargets(4)));
	}

	public function testAGroupIsReadWithWhatItAllows(): void {
		$group = $this->groups->group(4);

		$this->assertNotNull($group);
		$this->assertSame(array(4, 'Moderators', 'Moderator', 0, 30, 60), array($group->id(), $group->title(), $group->userTitle(), $group->postFlood(), $group->searchFlood(), $group->emailFlood()));
		$this->assertSame(array('g_moderator', 'g_read_board', 'g_post_replies', 'g_edit_posts'), array_values(array_map(static fn (GroupPermission $permission): string => $permission->value, array_filter(GroupPermission::cases(), static fn (GroupPermission $permission): bool => $group->allows($permission->value)))));
		$this->assertNull($this->groups->group(2)?->userTitle());
		$this->assertSame('Banned', $this->groups->baseGroup(5)?->title());
		$this->assertNull($this->groups->group(9));
	}

	public function testATitleIsTakenByAnotherGroup(): void {
		$this->assertTrue($this->groups->titleTaken('Members', null));
		$this->assertTrue($this->groups->titleTaken('Members', 4));
		$this->assertFalse($this->groups->titleTaken('Members', 3));
		$this->assertFalse($this->groups->titleTaken('Members\' club', null));
	}

	public function testAGroupIsAddedWithTheForumsPermissionsOfItsBaseAndUpdated(): void {
		$this->groups->add(new Group(0, 'Members\' club', null, array(GroupPermission::ReadBoard, GroupPermission::Search), 10, 20, 30));
		$id = $this->groups->lastAddedId();
		$this->groups->addForumPermissions($id, ...$this->groups->forumPermissions(3));

		$this->assertSame(6, $id);
		$added = $this->db->selectRow('SELECT * FROM pun_groups WHERE g_id=6');
		$this->assertSame(array('Members\' club', null, 1, 0, 1, 10, 20, 30, 7), array($added?->string('g_title'), $added?->nullableString('g_user_title'), $added?->int('g_read_board'), $added?->int('g_post_replies'), $added?->int('g_search'), $added?->int('g_post_flood'), $added?->int('g_search_flood'), $added?->int('g_email_flood'), $added?->int('g_probe')));
		$this->assertSame(array('1: 100', '2: 000'), array_map(static fn ($row): string => $row->int('forum_id').': '.$row->int('read_forum').$row->int('post_replies').$row->int('post_topics'), $this->db->select('SELECT * FROM pun_forum_perms WHERE group_id=6 ORDER BY forum_id')));

		$this->groups->update(new Group(6, 'Club', 'Clubber', array(GroupPermission::Moderate, GroupPermission::BanUsers), 1, 2, 3));

		$updated = $this->groups->group(6);
		$this->assertSame(array('Club', 'Clubber', true, true, false, 1, 2, 3), array($updated?->title(), $updated?->userTitle(), $updated?->allows('g_moderator'), $updated?->allows('g_mod_ban_users'), $updated?->allows('g_read_board'), $updated?->postFlood(), $updated?->searchFlood(), $updated?->emailFlood()));
		$this->assertCount(2, $this->groups->forumPermissions(6));
		$this->assertEquals(array(new ForumPermissions(1, true, true, true)), $this->groups->forumPermissions(4));
	}

	public function testOnlyAGroupThatDoesNotModerateBecomesTheDefault(): void {
		$this->assertTrue($this->groups->isDefaultCandidate(5));
		$this->assertFalse($this->groups->isDefaultCandidate(4));
		$this->assertFalse($this->groups->isDefaultCandidate(9));

		$this->groups->makeDefault(5);

		$this->assertSame('5', $this->db->selectValue('SELECT conf_value FROM pun_config WHERE conf_name=\'o_default_user_group\''));
		$this->assertSame('Board', $this->db->selectValue('SELECT conf_value FROM pun_config WHERE conf_name=\'o_board_title\''));
	}

	public function testAGroupIsRemovedWithItsMembersMovedAndItsForumsPermissions(): void {
		$members = $this->groups->members(3);
		$this->assertSame(array('Members', 2), array($members?->title(), $members?->count()));
		$this->assertNull($this->groups->members(5));
		$this->assertNull($this->groups->members(9));

		$this->groups->moveMembers(5, 3);
		$this->groups->remove(3);
		$this->groups->removeForumPermissions(3);

		$this->assertNull($this->groups->group(3));
		$this->assertSame(array('3 5', '4 5'), array_map(static fn ($row): string => $row->int('id').' '.$row->int('group_id'), $this->db->select('SELECT id, group_id FROM pun_users WHERE id IN (3, 4)')));
		$this->assertSame(array(4), array_map(static fn ($row): int => $row->int('group_id'), $this->db->select('SELECT group_id FROM pun_forum_perms')));
	}
}
