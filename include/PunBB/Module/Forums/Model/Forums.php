<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Forums\Api\Data\ForumInterface;
use PunBB\Module\Forums\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Forums\Api\Data\ForumPositionInterface;
use PunBB\Module\Forums\Api\Data\GroupDefaultsInterface;
use PunBB\Module\Forums\Api\ForumsInterface;

/**
 * The forums, read from and written to the forums table with the categories
 * they are listed under, the groups' board permissions and the permissions
 * each forum stores in forum_perms.
 */
final class Forums implements ForumsInterface {
	public function __construct(private readonly Connection $db) {}

	public function all(): array {
		return array_map(static fn (Row $row): ListedForum => new ListedForum($row->int('cid'), $row->string('cat_name'), $row->int('fid'), $row->string('forum_name'), $row->int('disp_position')),
			$this->db->select('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.disp_position FROM '.$this->db->table('categories').' AS c INNER JOIN '.$this->db->table('forums').' AS f ON c.id=f.cat_id ORDER BY c.disp_position, c.id, f.disp_position'));
	}

	public function categories(): array {
		return array_map(static fn (Row $row): Category => new Category($row->int('id'), $row->string('cat_name')),
			$this->db->select('SELECT c.id, c.cat_name FROM '.$this->db->table('categories').' AS c ORDER BY c.disp_position'));
	}

	public function assignableCategories(): array {
		return $this->categories();
	}

	public function categoryExists(int $id): bool {
		return (int) $this->db->selectValue('SELECT COUNT(c.id) FROM '.$this->db->table('categories').' AS c WHERE c.id=?', $id) === 1;
	}

	public function add(ForumInterface ...$forums): void {
		foreach ($forums as $forum)
			$this->db->execute('INSERT INTO '.$this->db->table('forums').' (forum_name, disp_position, cat_id) VALUES (?, ?, ?)', $forum->name(), $forum->position(), $forum->categoryId());
	}

	public function find(int $id): ?ForumInterface {
		$row = $this->db->selectRow('SELECT f.id, f.forum_name, f.forum_desc, f.redirect_url, f.num_topics, f.sort_by, f.cat_id FROM '.$this->db->table('forums').' AS f WHERE f.id=?', $id);

		return $row !== null ? new Forum($row->int('id'), $row->string('forum_name'), $row->nullableString('forum_desc'), $row->nullableString('redirect_url'), $row->int('sort_by'), $row->int('cat_id'), 0, $row->int('num_topics')) : null;
	}

	public function name(int $id): ?string {
		$name = $this->db->selectValue('SELECT f.forum_name FROM '.$this->db->table('forums').' AS f WHERE f.id=?', $id);

		return $name !== null ? (string) $name : null;
	}

	public function update(ForumInterface ...$forums): void {
		foreach ($forums as $forum)
			$this->db->execute('UPDATE '.$this->db->table('forums').' SET forum_name=?, forum_desc=?, redirect_url=?, sort_by=?, cat_id=? WHERE id=?',
				$forum->name(), $forum->description(), $forum->redirectUrl(), $forum->sortBy(), $forum->categoryId(), $forum->id());
	}

	public function positions(): array {
		return array_map(static fn (Row $row): ForumPosition => new ForumPosition($row->int('id'), $row->int('disp_position')),
			$this->db->select('SELECT f.id, f.disp_position FROM '.$this->db->table('categories').' AS c INNER JOIN '.$this->db->table('forums').' AS f ON c.id=f.cat_id ORDER BY c.disp_position, c.id, f.disp_position'));
	}

	public function reposition(ForumPositionInterface ...$positions): void {
		foreach ($positions as $position)
			$this->db->execute('UPDATE '.$this->db->table('forums').' SET disp_position=? WHERE id=?', $position->position(), $position->forumId());
	}

	public function remove(int ...$ids): void {
		foreach ($ids as $id)
			$this->db->execute('DELETE FROM '.$this->db->table('forums').' WHERE id=?', $id);
	}

	public function removePermissions(int ...$forumIds): void {
		foreach ($forumIds as $forumId)
			$this->db->execute('DELETE FROM '.$this->db->table('forum_perms').' WHERE forum_id=?', $forumId);
	}

	public function removeSubscriptions(int ...$forumIds): void {
		foreach ($forumIds as $forumId)
			$this->db->execute('DELETE FROM '.$this->db->table('forum_subscriptions').' WHERE forum_id=?', $forumId);
	}

	public function groupDefaults(): array {
		return array_map(static fn (Row $row): GroupDefaults => new GroupDefaults($row->int('g_id'), $row->int('g_read_board') === 1, $row->int('g_post_replies') === 1, $row->int('g_post_topics') === 1),
			$this->db->select('SELECT g.g_id, g.g_read_board, g.g_post_replies, g.g_post_topics FROM '.$this->db->table('groups').' AS g WHERE g.g_id!=?', GroupDefaultsInterface::ADMINISTRATORS));
	}

	public function groupPermissions(int $forumId): array {
		return array_map(static fn (Row $row): GroupPermissions => new GroupPermissions(
			$row->int('g_id'),
			$row->string('g_title'),
			$row->int('g_read_board') === 1,
			$row->int('g_post_replies') === 1,
			$row->int('g_post_topics') === 1,
			self::stored($row, 'read_forum'),
			self::stored($row, 'post_replies'),
			self::stored($row, 'post_topics')
		), $this->db->select('SELECT g.g_id, g.g_title, g.g_read_board, g.g_post_replies, g.g_post_topics, fp.read_forum, fp.post_replies, fp.post_topics FROM '.$this->db->table('groups').' AS g LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON g.g_id=fp.group_id AND fp.forum_id=? WHERE g.g_id!=? ORDER BY g.g_id', $forumId, GroupDefaultsInterface::ADMINISTRATORS));
	}

	public function permissionsStored(int $forumId, int $groupId): bool {
		return (int) $this->db->selectValue('SELECT COUNT(fp.group_id) FROM '.$this->db->table('forum_perms').' AS fp WHERE fp.group_id=? AND fp.forum_id=?', $groupId, $forumId) > 0;
	}

	public function updatePermissions(int $forumId, ForumPermissionsInterface ...$permissions): void {
		foreach ($permissions as $permission)
			$this->db->execute('UPDATE '.$this->db->table('forum_perms').' SET read_forum=?, post_replies=?, post_topics=? WHERE group_id=? AND forum_id=?',
				$permission->readForum(), $permission->postReplies(), $permission->postTopics(), $permission->groupId(), $forumId);
	}

	public function addPermissions(int $forumId, ForumPermissionsInterface ...$permissions): void {
		foreach ($permissions as $permission)
			$this->db->execute('INSERT INTO '.$this->db->table('forum_perms').' (group_id, forum_id, read_forum, post_replies, post_topics) VALUES (?, ?, ?, ?, ?)',
				$permission->groupId(), $forumId, $permission->readForum(), $permission->postReplies(), $permission->postTopics());
	}

	public function removeGroupPermissions(int $forumId, int ...$groupIds): void {
		foreach ($groupIds as $groupId)
			$this->db->execute('DELETE FROM '.$this->db->table('forum_perms').' WHERE group_id=? AND forum_id=?', $groupId, $forumId);
	}

	public function revertPermissions(int ...$forumIds): void {
		$this->removePermissions(...$forumIds);
	}

	/** A permission the forum stores, which allows unless it is 0; null when the forum stores none for the group. */
	private static function stored(Row $row, string $column): ?bool {
		$value = $row->nullableInt($column);

		return $value !== null ? $value !== 0 : null;
	}
}
