<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Groups\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Groups\Api\Data\GroupInterface;
use PunBB\Module\Groups\Api\Data\GroupMembersInterface;
use PunBB\Module\Groups\Api\GroupsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;

/**
 * The groups, read from and written to the groups table, with the permissions
 * the forums store for them in forum_perms, their members in users and the
 * default group in config.
 */
final class Groups implements GroupsInterface {
	public function __construct(private readonly Connection $db) {}

	public function all(): array {
		return self::listed($this->db->select('SELECT g.g_id, g.g_title FROM '.$this->db->table('groups').' AS g ORDER BY g.g_title'));
	}

	public function baseGroups(): array {
		return self::listed($this->db->select('SELECT g.g_id, g.g_title FROM '.$this->db->table('groups').' AS g WHERE g.g_id>? ORDER BY g.g_title', GroupInterface::GUESTS));
	}

	public function defaultCandidates(): array {
		return self::listed($this->db->select('SELECT g.g_id, g.g_title FROM '.$this->db->table('groups').' AS g WHERE g.g_id>? AND g.g_moderator=0 ORDER BY g.g_title', GroupInterface::GUESTS));
	}

	public function moveTargets(int $id): array {
		return self::listed($this->db->select('SELECT g.g_id, g.g_title FROM '.$this->db->table('groups').' AS g WHERE g.g_id!=? AND g.g_id!=? ORDER BY g.g_title', GroupInterface::GUESTS, $id));
	}

	public function baseGroup(int $id): ?GroupInterface {
		return $this->group($id);
	}

	public function group(int $id): ?GroupInterface {
		$row = $this->db->selectRow('SELECT g.* FROM '.$this->db->table('groups').' AS g WHERE g.g_id=?', $id);
		if ($row === null)
			return null;

		$permissions = array();
		foreach (GroupPermission::cases() as $permission)
			if ($row->int($permission->value) === 1)
				$permissions[] = $permission;

		return new Group($row->int('g_id'), $row->string('g_title'), $row->nullableString('g_user_title'), $permissions, $row->int('g_post_flood'), $row->int('g_search_flood'), $row->int('g_email_flood'));
	}

	public function titleTaken(string $title, ?int $exceptId): bool {
		$sql = 'SELECT COUNT(g.g_id) FROM '.$this->db->table('groups').' AS g WHERE g.g_title=?';

		return (int) ($exceptId !== null ? $this->db->selectValue($sql.' AND g.g_id!=?', $title, $exceptId) : $this->db->selectValue($sql, $title)) !== 0;
	}

	public function add(GroupInterface ...$groups): void {
		$columns = self::columns();

		foreach ($groups as $group)
			$this->db->execute('INSERT INTO '.$this->db->table('groups').' ('.implode(', ', $columns).') VALUES ('.implode(', ', array_fill(0, count($columns), '?')).')', ...self::values($group));
	}

	public function lastAddedId(): int {
		return $this->db->lastInsertId();
	}

	public function update(GroupInterface ...$groups): void {
		$set = implode(', ', array_map(static fn (string $column): string => $column.'=?', self::columns()));

		foreach ($groups as $group)
			$this->db->execute('UPDATE '.$this->db->table('groups').' SET '.$set.' WHERE g_id=?', ...array(...self::values($group), $group->id()));
	}

	public function forumPermissions(int $groupId): array {
		return array_map(static fn (Row $row): ForumPermissions => new ForumPermissions($row->int('forum_id'), $row->int('read_forum') !== 0, $row->int('post_replies') !== 0, $row->int('post_topics') !== 0),
			$this->db->select('SELECT fp.forum_id, fp.read_forum, fp.post_replies, fp.post_topics FROM '.$this->db->table('forum_perms').' AS fp WHERE fp.group_id=?', $groupId));
	}

	public function addForumPermissions(int $groupId, ForumPermissionsInterface ...$permissions): void {
		foreach ($permissions as $permission)
			$this->db->execute('INSERT INTO '.$this->db->table('forum_perms').' (group_id, forum_id, read_forum, post_replies, post_topics) VALUES (?, ?, ?, ?, ?)',
				$groupId, $permission->forumId(), $permission->readForum(), $permission->postReplies(), $permission->postTopics());
	}

	public function isDefaultCandidate(int $id): bool {
		return (int) $this->db->selectValue('SELECT COUNT(g.g_id) FROM '.$this->db->table('groups').' AS g WHERE g.g_id=? AND g.g_moderator=0', $id) === 1;
	}

	public function makeDefault(int ...$ids): void {
		foreach ($ids as $id)
			$this->db->execute('UPDATE '.$this->db->table('config').' SET conf_value=? WHERE conf_name=?', (string) $id, 'o_default_user_group');
	}

	public function members(int $id): ?GroupMembersInterface {
		$row = $this->db->selectRow('SELECT g.g_title AS title, COUNT(u.id) AS num_members FROM '.$this->db->table('groups').' AS g INNER JOIN '.$this->db->table('users').' AS u ON g.g_id=u.group_id WHERE g.g_id=? GROUP BY g.g_id, g.g_title', $id);

		return $row !== null ? new GroupMembers($row->string('title'), $row->int('num_members')) : null;
	}

	public function moveMembers(int $toId, int ...$fromIds): void {
		foreach ($fromIds as $fromId)
			$this->db->execute('UPDATE '.$this->db->table('users').' SET group_id=? WHERE group_id=?', $toId, $fromId);
	}

	public function remove(int ...$ids): void {
		foreach ($ids as $id)
			$this->db->execute('DELETE FROM '.$this->db->table('groups').' WHERE g_id=?', $id);
	}

	public function removeForumPermissions(int ...$groupIds): void {
		foreach ($groupIds as $groupId)
			$this->db->execute('DELETE FROM '.$this->db->table('forum_perms').' WHERE group_id=?', $groupId);
	}

	/** @return list<string> the columns a group is stored in, as the page script listed them */
	private static function columns(): array {
		return array('g_title', 'g_user_title', ...array_map(static fn (GroupPermission $permission): string => $permission->value, GroupPermission::cases()), 'g_post_flood', 'g_search_flood', 'g_email_flood');
	}

	/** @return list<int|string|bool|null> $group's values, in the order of columns() */
	private static function values(GroupInterface $group): array {
		return array($group->title(), $group->userTitle(), ...array_map(static fn (GroupPermission $permission): bool => $group->allows($permission->value), GroupPermission::cases()), $group->postFlood(), $group->searchFlood(), $group->emailFlood());
	}

	/**
	 * @param list<Row> $rows
	 * @return list<ListedGroup>
	 */
	private static function listed(array $rows): array {
		return array_map(static fn (Row $row): ListedGroup => new ListedGroup($row->int('g_id'), $row->string('g_title')), $rows);
	}
}
