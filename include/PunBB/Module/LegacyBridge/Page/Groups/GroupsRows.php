<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Groups;

use PunBB\Module\Groups\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Groups\Api\Data\GroupInterface;
use PunBB\Module\Groups\Api\Data\ListedGroupInterface;
use PunBB\Module\Groups\Model\Group;
use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\Site\Visitor\GroupPermission;

/**
 * The groups and their forums' permissions as admin/groups.php handed them to
 * extension code: the row of their query, with any column a query point added,
 * and a submitted group in the variables the page script held it in.
 */
final class GroupsRows {
	public function __construct(private readonly KeptRows $rows) {}

	/** @param array<array-key, mixed> $row */
	public function keep(object $answer, array $row): void {
		$this->rows->keep($answer, $row);
	}

	/** @return array<array-key, mixed> a group of a list, as the page script read it */
	public function listed(ListedGroupInterface $group): array {
		return $this->rows->row($group) ?? array('g_id' => $group->id(), 'g_title' => $group->title());
	}

	/** @return array<array-key, mixed> a forum's permissions for the group a new one is based on, as the page script read them */
	public function forumPermissions(ForumPermissionsInterface $permissions): array {
		return $this->rows->row($permissions) ?? array(
			'forum_id'		=> $permissions->forumId(),
			'read_forum'	=> (int) $permissions->readForum(),
			'post_replies'	=> (int) $permissions->postReplies(),
			'post_topics'	=> (int) $permissions->postTopics(),
		);
	}

	/** @param array<array-key, mixed> $row a row of the groups table */
	public static function group(array $row): Group {
		$permissions = array();
		foreach (GroupPermission::cases() as $permission)
			if (Markers::markup($row[$permission->value] ?? 0) === '1')
				$permissions[] = $permission;

		return new Group(
			(int) Markers::markup($row['g_id'] ?? 0),
			Markers::markup($row['g_title'] ?? ''),
			isset($row['g_user_title']) ? Markers::markup($row['g_user_title']) : null,
			$permissions,
			(int) Markers::markup($row['g_post_flood'] ?? 0),
			(int) Markers::markup($row['g_search_flood'] ?? 0),
			(int) Markers::markup($row['g_email_flood'] ?? 0)
		);
	}

	/**
	 * The variables the page script held a submitted group in, named after the
	 * columns without their g_ and in their order: the title as text, the user
	 * title quoted for SQL or NULL, each permission '1' or '0', the intervals.
	 *
	 * @return array<string, string|int>
	 */
	public static function variables(GroupInterface $group): array {
		$userTitle = $group->userTitle();
		$variables = array('title' => $group->title(), 'user_title' => $userTitle !== null ? '\''.Markers::markup(LegacyConnection::legacy()->escape($userTitle)).'\'' : 'NULL');

		foreach (GroupPermission::cases() as $permission)
			$variables[substr($permission->value, 2)] = $group->allows($permission->value) ? '1' : '0';

		return $variables + array('post_flood' => $group->postFlood(), 'search_flood' => $group->searchFlood(), 'email_flood' => $group->emailFlood());
	}
}
