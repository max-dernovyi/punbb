<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Groups;

use PunBB\Module\Groups\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Groups\Api\Data\GroupInterface;
use PunBB\Module\Groups\Api\Data\GroupMembersInterface;
use PunBB\Module\Groups\Api\Data\ListedGroupInterface;
use PunBB\Module\Groups\Api\GroupsInterface;
use PunBB\Module\Groups\Model\ForumPermissions;
use PunBB\Module\Groups\Model\GroupMembers;
use PunBB\Module\Groups\Model\ListedGroup;
use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;

/**
 * The groups page's query points, with the query arrays admin/groups.php
 * built. A query a point changed answers instead, its rows kept with any
 * column it added; a statement a point changed runs instead, and the
 * repository is handed nothing to store. What was read is left where the page
 * script kept it: the group edited or based on in $group, with every column
 * the table has, and its id in $group_id or $base_group; a new group's id in
 * $new_group_id; a removed group's title and member count in $group_info.
 */
final class GroupsPlugin {
	public function __construct(private readonly PluggedQuery $queries, private readonly GroupsRows $rows) {}

	/**
	 * @param list<ListedGroupInterface> $result
	 * @return list<ListedGroupInterface>
	 */
	public function afterAll(GroupsInterface $subject, array $result): array {
		return $this->listed('agr_qr_get_group_list', 'all', null, $result);
	}

	/**
	 * @param list<ListedGroupInterface> $result
	 * @return list<ListedGroupInterface>
	 */
	public function afterBaseGroups(GroupsInterface $subject, array $result): array {
		return $this->listed('agr_qr_get_allowed_base_groups', 'baseGroups', 'g_id>'.GroupInterface::GUESTS, $result);
	}

	/**
	 * @param list<ListedGroupInterface> $result
	 * @return list<ListedGroupInterface>
	 */
	public function afterDefaultCandidates(GroupsInterface $subject, array $result): array {
		return $this->listed('agr_qr_get_groups', 'defaultCandidates', 'g_id>'.GroupInterface::GUESTS.' AND g_moderator=0', $result);
	}

	/**
	 * @param list<ListedGroupInterface> $result
	 * @return list<ListedGroupInterface>
	 */
	public function afterMoveTargets(GroupsInterface $subject, array $result, int $id): array {
		return $this->listed('agr_del_group_qr_get_groups', 'moveTargets', 'g.g_id!='.GroupInterface::GUESTS.' AND g.g_id!='.$id, $result);
	}

	public function afterBaseGroup(GroupsInterface $subject, ?GroupInterface $result, int $id): ?GroupInterface {
		$GLOBALS['base_group'] = $id;

		return $this->group('agr_add_group_qr_get_base_group', 'baseGroup', $id, $result);
	}

	public function afterGroup(GroupsInterface $subject, ?GroupInterface $result, int $id): ?GroupInterface {
		$GLOBALS['group_id'] = $id;

		return $this->group('agr_edit_group_qr_get_group', 'group', $id, $result);
	}

	public function afterTitleTaken(GroupsInterface $subject, bool $result, string $title, ?int $exceptId): bool {
		$query = array(
			'SELECT'	=> 'COUNT(g.g_id)',
			'FROM'		=> 'groups AS g',
			'WHERE'		=> 'g_title=\''.self::escape($title).'\''.($exceptId !== null ? ' AND g_id!='.$exceptId : '')
		);

		$point = $exceptId !== null ? 'agr_edit_end_qr_check_edit_group_title_collision' : 'agr_add_end_qr_check_add_group_title_collision';
		if ($this->queries->changed($point, GroupsInterface::class.'::titleTaken', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query)) !== 0;

		return $result;
	}

	/** @return list<GroupInterface>|null */
	public function beforeAdd(GroupsInterface $subject, GroupInterface ...$groups): ?array {
		$kept = array();
		foreach ($groups as $group)
		{
			$values = self::values($group);

			$query = array(
				'INSERT'	=> implode(', ', array_keys($values)),
				'INTO'		=> 'groups',
				'VALUES'	=> implode(', ', $values)
			);

			if ($this->queries->changed('agr_add_end_qr_add_group', GroupsInterface::class.'::add', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $group;
		}

		return count($kept) !== count($groups) ? $kept : null;
	}

	public function afterLastAddedId(GroupsInterface $subject, int $result): int {
		$GLOBALS['new_group_id'] = $result;

		return $result;
	}

	/** @return list<GroupInterface>|null */
	public function beforeUpdate(GroupsInterface $subject, GroupInterface ...$groups): ?array {
		$kept = array();
		foreach ($groups as $group)
		{
			$set = array();
			foreach (self::values($group) as $column => $value)
				$set[] = $column.'='.$value;

			$query = array(
				'UPDATE'	=> 'groups',
				'SET'		=> implode(', ', $set),
				'WHERE'		=> 'g_id='.$group->id()
			);

			if ($this->queries->changed('agr_edit_end_qr_update_group', GroupsInterface::class.'::update', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $group;
		}

		return count($kept) !== count($groups) ? $kept : null;
	}

	/**
	 * @param list<ForumPermissionsInterface> $result
	 * @return list<ForumPermissionsInterface>
	 */
	public function afterForumPermissions(GroupsInterface $subject, array $result, int $groupId): array {
		$query = array(
			'SELECT'	=> 'fp.forum_id, fp.read_forum, fp.post_replies, fp.post_topics',
			'FROM'		=> 'forum_perms AS fp',
			'WHERE'		=> 'group_id='.$groupId
		);

		if ($this->queries->changed('agr_add_end_qr_get_group_forum_perms', GroupsInterface::class.'::forumPermissions', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$permissions = new ForumPermissions((int) Markers::markup($row['forum_id'] ?? 0), Markers::markup($row['read_forum'] ?? 0) !== '0', Markers::markup($row['post_replies'] ?? 0) !== '0', Markers::markup($row['post_topics'] ?? 0) !== '0');
				$this->rows->keep($permissions, $row);
				$result[] = $permissions;
			}
		}

		return $result;
	}

	/** @return list<int|ForumPermissionsInterface>|null */
	public function beforeAddForumPermissions(GroupsInterface $subject, int $groupId, ForumPermissionsInterface ...$permissions): ?array {
		$kept = array();
		foreach ($permissions as $permission)
		{
			$GLOBALS['cur_forum_perm'] = $this->rows->forumPermissions($permission);

			$query = array(
				'INSERT'	=> 'group_id, forum_id, read_forum, post_replies, post_topics',
				'INTO'		=> 'forum_perms',
				'VALUES'	=> $groupId.', '.$permission->forumId().', '.(int) $permission->readForum().', '.(int) $permission->postReplies().', '.(int) $permission->postTopics()
			);

			if ($this->queries->changed('agr_add_end_qr_add_group_forum_perms', GroupsInterface::class.'::addForumPermissions', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $permission;
		}

		return count($kept) !== count($permissions) ? array($groupId, ...$kept) : null;
	}

	public function afterIsDefaultCandidate(GroupsInterface $subject, bool $result, int $id): bool {
		$query = array(
			'SELECT'	=> 'COUNT(g.g_id)',
			'FROM'		=> 'groups AS g',
			'WHERE'		=> 'g.g_id='.$id.' AND g.g_moderator=0',
			'LIMIT'		=> '1'
		);

		if ($this->queries->changed('agr_set_default_group_qr_get_group_moderation_status', GroupsInterface::class.'::isDefaultCandidate', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query)) === 1;

		return $result;
	}

	/** @return list<int>|null */
	public function beforeMakeDefault(GroupsInterface $subject, int ...$ids): ?array {
		return $this->statements($ids, 'agr_set_default_group_qr_set_default_group', 'makeDefault', static fn (int $id): array => array(
			'UPDATE'	=> 'config',
			'SET'		=> 'conf_value='.$id,
			'WHERE'		=> 'conf_name=\'o_default_user_group\''
		));
	}

	public function afterMembers(GroupsInterface $subject, ?GroupMembersInterface $result, int $id): ?GroupMembersInterface {
		$query = array(
			'SELECT'	=> 'g.g_title AS title, COUNT(u.id) AS num_members',
			'FROM'		=> 'groups AS g',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'users AS u',
					'ON'			=> 'g.g_id=u.group_id'
				)
			),
			'WHERE'		=> 'g.g_id='.$id,
			'GROUP BY'	=> 'g.g_id, g.g_title'
		);

		// The page script read the row by column position
		if ($this->queries->changed('agr_del_group_qr_get_group_member_count', GroupsInterface::class.'::members', $query))
		{
			$row = PluggedQuery::listed($query);
			$result = $row !== null ? new GroupMembers(Markers::markup($row[0] ?? ''), (int) Markers::markup($row[1] ?? 0)) : null;
		}

		$GLOBALS['group_info'] = $result !== null ? array($result->title(), $result->count()) : false;

		return $result;
	}

	/** @return list<int>|null */
	public function beforeMoveMembers(GroupsInterface $subject, int $toId, int ...$fromIds): ?array {
		$kept = $this->statements($fromIds, 'agr_del_group_qr_move_users', 'moveMembers', static fn (int $fromId): array => array(
			'UPDATE'	=> 'users',
			'SET'		=> 'group_id='.$toId,
			'WHERE'		=> 'group_id='.$fromId
		));

		return $kept !== null ? array($toId, ...$kept) : null;
	}

	/** @return list<int>|null */
	public function beforeRemove(GroupsInterface $subject, int ...$ids): ?array {
		return $this->statements($ids, 'agr_del_group_qr_delete_group', 'remove', static fn (int $id): array => array(
			'DELETE'	=> 'groups',
			'WHERE'		=> 'g_id='.$id
		));
	}

	/** @return list<int>|null */
	public function beforeRemoveForumPermissions(GroupsInterface $subject, int ...$groupIds): ?array {
		return $this->statements($groupIds, 'agr_del_group_qr_delete_group_forum_perms', 'removeForumPermissions', static fn (int $groupId): array => array(
			'DELETE'	=> 'forum_perms',
			'WHERE'		=> 'group_id='.$groupId
		));
	}

	/**
	 * @param list<ListedGroupInterface> $result
	 * @return list<ListedGroupInterface>
	 */
	private function listed(string $point, string $method, ?string $where, array $result): array {
		$query = array(
			'SELECT'	=> 'g.g_id, g.g_title',
			'FROM'		=> 'groups AS g',
		);

		if ($where !== null)
			$query['WHERE'] = $where;

		$query['ORDER BY'] = 'g.g_title';

		if ($this->queries->changed($point, GroupsInterface::class.'::'.$method, $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$group = new ListedGroup((int) Markers::markup($row['g_id'] ?? 0), Markers::markup($row['g_title'] ?? ''));
				$this->rows->keep($group, $row);
				$result[] = $group;
			}
		}

		return $result;
	}

	private function group(string $point, string $method, int $id, ?GroupInterface $result): ?GroupInterface {
		$query = array(
			'SELECT'	=> 'g.*',
			'FROM'		=> 'groups AS g',
			'WHERE'		=> 'g.g_id='.$id
		);

		$changed = $this->queries->changed($point, GroupsInterface::class.'::'.$method, $query);

		// g.* carries the columns extensions add to the groups table, so the row is read as the page script read it
		$row = PluggedQuery::rows($query)[0] ?? null;
		if ($changed)
			$result = $row !== null ? GroupsRows::group($row) : null;

		$GLOBALS['group'] = $row ?? false;

		return $result;
	}

	/**
	 * Runs $point over the statement $build makes for each id.
	 *
	 * @param array<int> $ids
	 * @param \Closure(int): array<string, mixed> $build
	 * @return list<int>|null
	 */
	private function statements(array $ids, string $point, string $method, \Closure $build): ?array {
		$kept = array();
		foreach ($ids as $id)
		{
			$query = $build($id);

			if ($this->queries->changed($point, GroupsInterface::class.'::'.$method, $query))
				PluggedQuery::run($query);
			else
				$kept[] = $id;
		}

		return count($kept) !== count($ids) ? $kept : null;
	}

	/** @return array<string, string> column => $group's value, as the page script's statements wrote it */
	private static function values(GroupInterface $group): array {
		$values = array();
		foreach (GroupsRows::variables($group) as $name => $value)
			$values['g_'.$name] = $name === 'title' ? '\''.self::escape((string) $value).'\'' : (string) $value;

		return $values;
	}

	private static function escape(string $text): string {
		return Markers::markup(LegacyConnection::legacy()->escape($text));
	}
}
