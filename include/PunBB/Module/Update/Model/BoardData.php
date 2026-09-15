<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Update\Api\BoardDataInterface;
use PunBB\Module\Update\Api\Data\PostRangeInterface;
use PunBB\Module\Update\Charset\ConversionException;

/**
 * The rows an update rewrites, over the forum's tables.
 */
final class BoardData implements BoardDataInterface {
	/** The permissions 1.2 kept for every moderator in the configuration. */
	private const MODERATOR_PERMISSIONS = array('g_mod_edit_users', 'g_mod_rename_users', 'g_mod_change_passwords', 'g_mod_ban_users');

	/** The group 1.2 marked an unverified account with. */
	private const UNVERIFIED_GROUP = 32000;

	public function __construct(private readonly Connection $db) {}

	public function reorderGroups(): void {
		$groups = $this->db->table('groups');

		$this->db->execute('UPDATE '.$groups.' SET g_moderator=1 WHERE g_id=2');

		$spare = (int) $this->db->selectValue('SELECT MAX(g.g_id) + 1 FROM '.$groups.' AS g');

		foreach (array('groups' => 'g_id', 'users' => 'group_id', 'forum_perms' => 'group_id') as $table => $column)
		{
			$sql = 'UPDATE '.$this->db->table($table).' SET '.$column.'=? WHERE '.$column.'=?';

			$this->db->execute($sql, $spare, 2);
			$this->db->execute($sql, 2, 3);
			$this->db->execute($sql, 3, 4);
			$this->db->execute($sql, 4, $spare);
		}
	}

	public function grantModerators(string $permission, int $value): void {
		if (!in_array($permission, self::MODERATOR_PERMISSIONS, true))
			throw new ConversionException(sprintf('"%s" is no moderator permission', $permission));

		$this->db->execute('UPDATE '.$this->db->table('groups').' SET '.$permission.'=? WHERE g_moderator=1', $value);
	}

	public function limitGroupMail(): void {
		$groups = $this->db->table('groups');

		$this->db->execute('UPDATE '.$groups.' SET g_send_email=0 WHERE g_id=2');
		$this->db->execute('UPDATE '.$groups.' SET g_email_flood=0 WHERE g_id IN (1,2,4)');
	}

	public function recordFirstPosts(): void {
		$firsts = $this->db->select('SELECT MIN(p.id) AS first_post, p.topic_id FROM '.$this->db->table('posts').' AS p GROUP BY p.topic_id');

		foreach ($firsts as $first)
			$this->db->execute('UPDATE '.$this->db->table('topics').' SET first_post_id=? WHERE id=?', $first->int('first_post'), $first->int('topic_id'));
	}

	public function moveUnverifiedUsers(): void {
		$this->db->execute('UPDATE '.$this->db->table('users').' SET group_id=0 WHERE group_id=?', self::UNVERIFIED_GROUP);
	}

	public function supersededHotfixes(string $version): array {
		return array_map(static fn (Row $row): string => $row->string('id'),
			$this->db->select('SELECT e.id FROM '.$this->db->table('extensions').' AS e WHERE e.id LIKE \'hotfix_%\' AND e.version != ?', $version));
	}

	public function removeExtension(string $id): void {
		$this->db->execute('DELETE FROM '.$this->db->table('extension_hooks').' WHERE extension_id=?', $id);
		$this->db->execute('DELETE FROM '.$this->db->table('extensions').' WHERE id=?', $id);
	}

	public function schemeLinkedinAddresses(): void {
		$profiles = $this->db->select('SELECT u.id, u.linkedin FROM '.$this->db->table('users').' AS u WHERE u.linkedin IS NOT NULL');

		foreach ($profiles as $profile)
		{
			$address = $profile->string('linkedin');
			$lower = strtolower($address);

			if ($address !== '' && !str_starts_with($lower, 'http://') && !str_starts_with($lower, 'https://'))
				$this->db->execute('UPDATE '.$this->db->table('users').' SET linkedin=? WHERE id=?', 'http://'.$address, $profile->int('id'));
		}
	}

	public function storeAvatar(int $userId, int $type, int $width, int $height): void {
		$this->db->execute('UPDATE '.$this->db->table('users').' SET avatar=?, avatar_height=?, avatar_width=? WHERE id=?', $type, $height, $width, $userId);
	}

	public function postRange(): PostRangeInterface {
		$row = $this->db->selectRow('SELECT MIN(p.id) AS lowest, MAX(p.id) AS highest, COUNT(p.id) AS posts FROM '.$this->db->table('posts').' AS p');

		return new PostRange($row?->nullableInt('lowest') ?? 0, $row?->nullableInt('highest') ?? 0, $row?->int('posts') ?? 0);
	}

	public function postText(int $postId): ?string {
		$row = $this->db->selectRow('SELECT p.message, p.poster, t.subject, f.forum_name FROM '.$this->db->table('posts').' AS p'.
			' INNER JOIN '.$this->db->table('topics').' AS t ON t.id=p.topic_id'.
			' INNER JOIN '.$this->db->table('forums').' AS f ON f.id=t.forum_id'.
			' WHERE p.id >= ? LIMIT 1', $postId);

		return $row !== null ? ($row->nullableString('message') ?? '').$row->string('poster').$row->string('subject').$row->string('forum_name') : null;
	}

	public function forumIds(): array {
		return array_map(static fn (Row $row): int => $row->int('id'), $this->db->select('SELECT f.id FROM '.$this->db->table('forums').' AS f'));
	}

	public function syncForum(int $forumId): void {
		$topics = $this->db->table('topics');

		$stats = $this->db->selectRow('SELECT COUNT(t.id) AS num_topics, SUM(t.num_replies) AS num_replies FROM '.$topics.' AS t WHERE t.forum_id=?', $forumId);
		$numTopics = $stats?->int('num_topics') ?? 0;

		// The replies of every topic, and the post each topic opens with
		$numPosts = ($stats?->nullableInt('num_replies') ?? 0) + $numTopics;

		$last = $this->db->selectRow('SELECT t.last_post, t.last_post_id, t.last_poster FROM '.$topics.' AS t WHERE t.forum_id=? AND t.moved_to IS NULL ORDER BY t.last_post DESC LIMIT 1', $forumId);

		$this->db->execute('UPDATE '.$this->db->table('forums').' SET num_topics=?, num_posts=?, last_post=?, last_post_id=?, last_poster=? WHERE id=?',
			$numTopics,
			$numPosts,
			$last?->int('last_post'),
			$last?->int('last_post_id'),
			$last !== null ? ($last->nullableString('last_poster') ?? '') : null,
			$forumId);
	}

	public function emptySearchCache(): void {
		$this->db->execute('DELETE FROM '.$this->db->table('search_cache'));
	}

	public function emptyOnline(): void {
		$this->db->execute('DELETE FROM '.$this->db->table('online'));
	}
}
