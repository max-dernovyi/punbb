<?php
/**
 * The forums page's repository over an in-memory SQLite database with the
 * forum's tables: the forums by category, the categories, a forum's details
 * and name, adding, updating, moving and removing forums, and the permissions
 * each group has in a forum.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Forums\Api\Data\GroupPermissionsInterface;
use PunBB\Module\Forums\Model\Forum;
use PunBB\Module\Forums\Model\ForumPermissions;
use PunBB\Module\Forums\Model\ForumPosition;
use PunBB\Module\Forums\Model\Forums;

class ForumsTest extends TestCase {
	private Connection $db;

	private Forums $forums;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');
		$this->db->execute('CREATE TABLE pun_categories (id INTEGER PRIMARY KEY, cat_name VARCHAR(80) NOT NULL, disp_position INTEGER NOT NULL DEFAULT 0)');
		$this->db->execute('CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80) NOT NULL DEFAULT \'New forum\', forum_desc TEXT, redirect_url VARCHAR(100), num_topics INTEGER NOT NULL DEFAULT 0, sort_by INTEGER NOT NULL DEFAULT 0, disp_position INTEGER NOT NULL DEFAULT 0, cat_id INTEGER NOT NULL DEFAULT 0)');
		$this->db->execute('CREATE TABLE pun_groups (g_id INTEGER PRIMARY KEY, g_title VARCHAR(50) NOT NULL, g_read_board INTEGER NOT NULL DEFAULT 1, g_post_replies INTEGER NOT NULL DEFAULT 1, g_post_topics INTEGER NOT NULL DEFAULT 1)');
		$this->db->execute('CREATE TABLE pun_forum_perms (group_id INTEGER NOT NULL, forum_id INTEGER NOT NULL, read_forum INTEGER NOT NULL DEFAULT 1, post_replies INTEGER NOT NULL DEFAULT 1, post_topics INTEGER NOT NULL DEFAULT 1, PRIMARY KEY (group_id, forum_id))');
		$this->db->execute('CREATE TABLE pun_forum_subscriptions (user_id INTEGER NOT NULL, forum_id INTEGER NOT NULL)');

		$this->db->execute('INSERT INTO pun_categories (id, cat_name, disp_position) VALUES (1, ?, 2), (2, ?, 1), (3, ?, 3)', 'Talk', 'News', 'Empty');
		$this->db->execute('INSERT INTO pun_forums (id, forum_name, forum_desc, redirect_url, num_topics, sort_by, disp_position, cat_id) VALUES (1, ?, ?, NULL, 4, 1, 2, 1), (2, ?, NULL, ?, 0, 0, 1, 1), (3, ?, NULL, NULL, 0, 0, 1, 2)',
			'General', '<b>All</b> talk', 'Elsewhere', 'http://example.com/', 'Announcements');
		$this->db->execute('INSERT INTO pun_groups (g_id, g_title, g_read_board, g_post_replies, g_post_topics) VALUES (1, ?, 1, 1, 1), (2, ?, 1, 0, 0), (3, ?, 1, 1, 1), (4, ?, 0, 0, 0)', 'Administrators', 'Guest', 'Members', 'Banned');
		$this->db->execute('INSERT INTO pun_forum_perms (group_id, forum_id, read_forum, post_replies, post_topics) VALUES (2, 1, 1, 1, 0), (3, 1, 0, 0, 0), (3, 2, 1, 1, 1)');
		$this->db->execute('INSERT INTO pun_forum_subscriptions (user_id, forum_id) VALUES (5, 1), (5, 3), (6, 1)');

		$this->forums = new Forums($this->db);
	}

	/** @return list<string> */
	private function stored(): array {
		return array_map(static fn ($row): string => $row->int('id').' '.$row->string('forum_name').' in '.$row->int('cat_id').'@'.$row->int('disp_position').' sort '.$row->int('sort_by').' desc '.var_export($row->nullableString('forum_desc'), true).' to '.var_export($row->nullableString('redirect_url'), true),
			$this->db->select('SELECT * FROM pun_forums ORDER BY id'));
	}

	/** @return list<string> */
	private function permissions(): array {
		return array_map(static fn ($row): string => $row->int('group_id').' in '.$row->int('forum_id').': '.$row->int('read_forum').$row->int('post_replies').$row->int('post_topics'),
			$this->db->select('SELECT * FROM pun_forum_perms ORDER BY forum_id, group_id'));
	}

	public function testTheForumsComeByCategoryPositionAndTheCategoriesByPosition(): void {
		$this->assertSame(array('2 News: 3 Announcements@1', '1 Talk: 2 Elsewhere@1', '1 Talk: 1 General@2'),
			array_map(static fn ($forum): string => $forum->categoryId().' '.$forum->categoryName().': '.$forum->id().' '.$forum->name().'@'.$forum->position(), $this->forums->all()));
		$this->assertSame(array('3@1', '2@1', '1@2'), array_map(static fn ($position): string => $position->forumId().'@'.$position->position(), $this->forums->positions()));

		foreach (array($this->forums->categories(), $this->forums->assignableCategories()) as $categories)
			$this->assertSame(array('2 News', '1 Talk', '3 Empty'), array_map(static fn ($category): string => $category->id().' '.$category->name(), $categories));

		$this->assertTrue($this->forums->categoryExists(3));
		$this->assertFalse($this->forums->categoryExists(9));
	}

	public function testAForumIsFoundWithItsDetailsAndNamed(): void {
		$forum = $this->forums->find(1);

		$this->assertNotNull($forum);
		$this->assertSame(array(1, 'General', '<b>All</b> talk', null, 1, 1, 4), array($forum->id(), $forum->name(), $forum->description(), $forum->redirectUrl(), $forum->sortBy(), $forum->categoryId(), $forum->topicCount()));
		$this->assertSame('http://example.com/', $this->forums->find(2)?->redirectUrl());
		$this->assertNull($this->forums->find(9));
		$this->assertSame('Elsewhere', $this->forums->name(2));
		$this->assertNull($this->forums->name(9));
	}

	public function testForumsAreAddedUpdatedMovedAndRemovedWithWhatTheyTakeAlong(): void {
		$this->forums->add(new Forum(99, 'Games \'n\' fun', 'ignored', categoryId: 3, position: 7));
		$this->forums->update(new Forum(3, 'Notices', null, null, 1, 2, 5), new Forum(1, 'General', 'Talk', 'http://example.org/', 0, 1));
		$this->forums->reposition(new ForumPosition(3, 4));
		$this->forums->remove(2);
		$this->forums->removePermissions(2);
		$this->forums->removeSubscriptions(1);

		$this->assertSame(array(
			'1 General in 1@2 sort 0 desc \'Talk\' to \'http://example.org/\'',
			'3 Notices in 2@4 sort 1 desc NULL to NULL',
			'4 Games \'n\' fun in 3@7 sort 0 desc NULL to NULL',
		), $this->stored());
		$this->assertSame(array('2 in 1: 110', '3 in 1: 000'), $this->permissions());
		$this->assertSame(array(3), array_map(static fn ($row): int => $row->int('forum_id'), $this->db->select('SELECT forum_id FROM pun_forum_subscriptions')));
	}

	public function testEveryGroupButTheAdministratorsHasItsDefaultsAndWhatTheForumStores(): void {
		$this->assertSame(array('2 100', '3 111', '4 000'), array_map(static fn ($group): string => $group->groupId().' '.(int) $group->readsBoard().(int) $group->postsReplies().(int) $group->postsTopics(), $this->forums->groupDefaults()));

		$listed = static fn (GroupPermissionsInterface $group): string => $group->groupId().' '.$group->groupTitle().' '.(int) $group->readsBoard().(int) $group->postsReplies().(int) $group->postsTopics()
			.' stored '.var_export($group->readForum(), true).'/'.var_export($group->postReplies(), true).'/'.var_export($group->postTopics(), true);

		$this->assertSame(array('2 Guest 100 stored true/true/false', '3 Members 111 stored false/false/false', '4 Banned 000 stored NULL/NULL/NULL'), array_map($listed, $this->forums->groupPermissions(1)));
		$this->assertSame(array('2 Guest 100 stored NULL/NULL/NULL', '3 Members 111 stored true/true/true', '4 Banned 000 stored NULL/NULL/NULL'), array_map($listed, $this->forums->groupPermissions(2)));
	}

	public function testAGroupsPermissionsInAForumAreStoredChangedRemovedAndReverted(): void {
		$this->assertTrue($this->forums->permissionsStored(1, 3));
		$this->assertFalse($this->forums->permissionsStored(3, 3));

		$this->forums->addPermissions(3, new ForumPermissions(4, true, false, true));
		$this->forums->updatePermissions(1, new ForumPermissions(3, true, true, false), new ForumPermissions(4, true, true, true));
		$this->forums->removeGroupPermissions(1, 2);

		$this->assertSame(array('3 in 1: 110', '3 in 2: 111', '4 in 3: 101'), $this->permissions());

		$this->forums->revertPermissions(1, 3);

		$this->assertSame(array('3 in 2: 111'), $this->permissions());
	}
}
