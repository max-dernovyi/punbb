<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Forums;

use PunBB\Module\Forums\Api\Data\ForumInterface;
use PunBB\Module\Forums\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Forums\Api\Data\GroupDefaultsInterface;
use PunBB\Module\Forums\Api\Data\GroupPermissionsInterface;
use PunBB\Module\Forums\Api\Data\ListedForumInterface;
use PunBB\Module\LegacyBridge\Page\KeptRows;

/**
 * The forums, groups and permissions as admin/forums.php handed them to
 * extension code: the row of their query, with any column a query point added.
 */
final class ForumsRows {
	public function __construct(private readonly KeptRows $rows) {}

	/** @param array<array-key, mixed> $row */
	public function keep(object $answer, array $row): void {
		$this->rows->keep($answer, $row);
	}

	/** @return array<array-key, mixed> a forum of the list, as the page script listed it */
	public function listed(ListedForumInterface $forum): array {
		return $this->rows->row($forum) ?? array(
			'cid'			=> $forum->categoryId(),
			'cat_name'		=> $forum->categoryName(),
			'fid'			=> $forum->id(),
			'forum_name'	=> $forum->name(),
			'disp_position'	=> $forum->position(),
		);
	}

	/** @return array<array-key, mixed> the forum being edited, as the page script read it */
	public function edited(ForumInterface $forum): array {
		return $this->rows->row($forum) ?? array(
			'id'			=> $forum->id(),
			'forum_name'	=> $forum->name(),
			'forum_desc'	=> $forum->description(),
			'redirect_url'	=> $forum->redirectUrl(),
			'num_topics'	=> $forum->topicCount(),
			'sort_by'		=> $forum->sortBy(),
			'cat_id'		=> $forum->categoryId(),
		);
	}

	/** @return array<array-key, mixed> a group whose permissions are saved, as the page script read it */
	public function group(GroupDefaultsInterface $group): array {
		return $this->rows->row($group) ?? array(
			'g_id'				=> $group->groupId(),
			'g_read_board'		=> (int) $group->readsBoard(),
			'g_post_replies'	=> (int) $group->postsReplies(),
			'g_post_topics'		=> (int) $group->postsTopics(),
		);
	}

	/** @return array<array-key, mixed> a group with its permissions in the forum, as the page script listed it */
	public function permissions(GroupPermissionsInterface $group): array {
		return $this->rows->row($group) ?? array(
			'g_id'				=> $group->groupId(),
			'g_title'			=> $group->groupTitle(),
			'g_read_board'		=> (int) $group->readsBoard(),
			'g_post_replies'	=> (int) $group->postsReplies(),
			'g_post_topics'		=> (int) $group->postsTopics(),
			'read_forum'		=> self::column($group->readForum()),
			'post_replies'		=> self::column($group->postReplies()),
			'post_topics'		=> self::column($group->postTopics()),
		);
	}

	/** @return array{read_forum: int, post_replies: int, post_topics: int} $permissions as the page script's $perms_* arrays held them */
	public static function values(ForumPermissionsInterface $permissions): array {
		return array(
			'read_forum'	=> (int) $permissions->readForum(),
			'post_replies'	=> (int) $permissions->postReplies(),
			'post_topics'	=> (int) $permissions->postTopics(),
		);
	}

	private static function column(?bool $value): ?int {
		return $value !== null ? (int) $value : null;
	}
}
