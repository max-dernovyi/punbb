<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Moderate\Api\Data\MergeTargetInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedForumInterface;
use PunBB\Module\Moderate\Api\Data\MovedTopicInterface;
use PunBB\Module\Moderate\Api\Data\RedirectTopicInterface;
use PunBB\Module\Moderate\Api\ModeratedTopicsInterface;

/**
 * A forum's topics, read from and written to the forums, categories, forum
 * permissions, topics, posts and subscriptions tables.
 */
final class ModeratedTopics implements ModeratedTopicsInterface {
	/** The columns of a listed topic; a list that also asks who posted groups by every one of them. */
	private const TOPIC_COLUMNS = 't.id, t.poster, t.subject, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to';

	public function __construct(private readonly Connection $db) {}

	public function forum(int $id, int $groupId): ?ModeratedForumInterface {
		$row = $this->db->selectRow('SELECT f.forum_name, f.redirect_url, f.num_topics, f.moderators, f.sort_by FROM '.$this->db->table('forums').' AS f'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)'.
			' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id=?', $groupId, $id);

		return $row !== null ? new ModeratedForum($id, $row->string('forum_name'), $row->nullableString('redirect_url') ?? '', $row->int('num_topics'),
			ModeratedForum::moderatorsOf($row->nullableString('moderators')), $row->int('sort_by') === 1) : null;
	}

	public function topics(int $forumId, bool $byPosted, int $offset, int $limit, ?int $postedBy): array {
		$order = ' ORDER BY t.sticky DESC, '.($byPosted ? 't.posted' : 't.last_post').' DESC LIMIT ? OFFSET ?';

		$rows = $postedBy === null
			? $this->db->select('SELECT '.self::TOPIC_COLUMNS.' FROM '.$this->db->table('topics').' AS t WHERE t.forum_id=?'.$order, $forumId, $limit, $offset)
			: $this->db->select('SELECT '.self::TOPIC_COLUMNS.', p.poster_id AS has_posted FROM '.$this->db->table('topics').' AS t'.
				' LEFT JOIN '.$this->db->table('posts').' AS p ON (p.poster_id=? AND p.topic_id=t.id)'.
				' WHERE t.forum_id=? GROUP BY '.self::TOPIC_COLUMNS.', p.poster_id'.$order, $postedBy, $forumId, $limit, $offset);

		return array_map(static fn (Row $row): ListedTopic => new ListedTopic(
			$row->int('id'),
			$row->string('poster'),
			$row->string('subject'),
			$row->int('posted'),
			$row->nullableInt('last_post') ?? 0,
			$row->nullableInt('last_post_id') ?? 0,
			$row->nullableString('last_poster') ?? '',
			$row->int('num_views'),
			$row->int('num_replies'),
			$row->int('closed') === 1,
			$row->int('sticky') === 1,
			$row->nullableInt('moved_to'),
			$postedBy !== null && $row->nullableInt('has_posted') === $postedBy
		), $rows);
	}

	public function subject(int $id): ?string {
		return self::text($this->db->selectValue('SELECT t.subject FROM '.$this->db->table('topics').' AS t WHERE t.id=?', $id));
	}

	public function subjectIn(int $id, int $forumId): ?string {
		return self::text($this->db->selectValue('SELECT t.subject FROM '.$this->db->table('topics').' AS t WHERE t.id=? AND t.forum_id=?', $id, $forumId));
	}

	public function moveTargets(int $exceptId, int $groupId): array {
		return array_map(static fn (Row $row): TargetForum => new TargetForum($row->int('cid'), $row->string('cat_name'), $row->int('fid'), $row->string('forum_name')),
			$this->db->select('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name FROM '.$this->db->table('categories').' AS c'.
				' INNER JOIN '.$this->db->table('forums').' AS f ON c.id=f.cat_id'.
				' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)'.
				' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.redirect_url IS NULL AND f.id!=?'.
				' ORDER BY c.disp_position, c.id, f.disp_position', $groupId, $exceptId));
	}

	public function forumName(int $id): ?string {
		return self::text($this->db->selectValue('SELECT f.forum_name FROM '.$this->db->table('forums').' AS f WHERE f.id=?', $id));
	}

	public function countTopics(int $forumId, int ...$topicIds): int {
		if ($topicIds === array())
			return 0;

		return (int) $this->db->selectValue('SELECT COUNT(t.id) FROM '.$this->db->table('topics').' AS t WHERE t.id IN('.ModeratedPosts::list($topicIds).') AND t.forum_id=?', $forumId);
	}

	public function removeRedirects(int $forumId, int ...$topicIds): void {
		if ($topicIds !== array())
			$this->db->execute('DELETE FROM '.$this->db->table('topics').' WHERE forum_id=? AND moved_to IN('.ModeratedPosts::list($topicIds).')', $forumId);
	}

	public function moveTopics(int $forumId, int ...$topicIds): void {
		if ($topicIds !== array())
			$this->db->execute('UPDATE '.$this->db->table('topics').' SET forum_id=? WHERE id IN('.ModeratedPosts::list($topicIds).')', $forumId);
	}

	public function movedTopic(int $id): ?MovedTopicInterface {
		$row = $this->db->selectRow('SELECT t.poster, t.subject, t.posted, t.last_post FROM '.$this->db->table('topics').' AS t WHERE t.id=?', $id);

		return $row !== null ? new MovedTopic($row->string('poster'), $row->string('subject'), $row->int('posted'), $row->nullableInt('last_post') ?? 0) : null;
	}

	public function addRedirects(RedirectTopicInterface ...$redirects): void {
		foreach ($redirects as $redirect)
			$this->db->execute('INSERT INTO '.$this->db->table('topics').' (poster, subject, posted, last_post, moved_to, forum_id) VALUES (?, ?, ?, ?, ?, ?)',
				$redirect->poster(), $redirect->subject(), $redirect->posted(), $redirect->lastPost(), $redirect->movedTo(), $redirect->forumId());
	}

	public function mergeTarget(int $forumId, int ...$topicIds): MergeTargetInterface {
		if ($topicIds === array())
			return new MergeTarget(0, null);

		$row = $this->db->selectRow('SELECT COUNT(t.id) AS num_topics, MIN(t.id) AS merge_to_tid FROM '.$this->db->table('topics').' AS t'.
			' WHERE t.id IN('.ModeratedPosts::list($topicIds).') AND t.moved_to IS NULL AND t.forum_id=?', $forumId);

		return new MergeTarget($row?->int('num_topics') ?? 0, $row?->nullableInt('merge_to_tid'));
	}

	public function redirectMerged(int $toId, bool $leaveRedirects, int ...$topicIds): void {
		if ($topicIds === array())
			return;

		$list = ModeratedPosts::list($topicIds);

		if ($leaveRedirects)
			$this->db->execute('UPDATE '.$this->db->table('topics').' SET moved_to=? WHERE moved_to IN('.$list.') OR (id IN('.$list.') AND id != ?)', $toId, $toId);
		else
			$this->db->execute('UPDATE '.$this->db->table('topics').' SET moved_to=? WHERE moved_to IN('.$list.')', $toId);
	}

	public function mergePosts(int $toId, int ...$topicIds): void {
		if ($topicIds !== array())
			$this->db->execute('UPDATE '.$this->db->table('posts').' SET topic_id=? WHERE topic_id IN('.ModeratedPosts::list($topicIds).')', $toId);
	}

	public function removeMergedSubscriptions(int $toId, int ...$topicIds): void {
		if ($topicIds !== array())
			$this->db->execute('DELETE FROM '.$this->db->table('subscriptions').' WHERE topic_id IN('.ModeratedPosts::list($topicIds).') AND topic_id != ?', $toId);
	}

	public function removeMergedTopics(int $toId, int ...$topicIds): void {
		if ($topicIds !== array())
			$this->db->execute('DELETE FROM '.$this->db->table('topics').' WHERE id IN('.ModeratedPosts::list($topicIds).') AND id != ?', $toId);
	}

	public function redirectForums(int ...$topicIds): array {
		if ($topicIds === array())
			return array();

		return array_map(static fn (Row $row): int => $row->int('forum_id'),
			$this->db->select('SELECT t.forum_id FROM '.$this->db->table('topics').' AS t WHERE t.moved_to IN('.ModeratedPosts::list($topicIds).')'));
	}

	public function removeTopics(int ...$topicIds): void {
		if ($topicIds === array())
			return;

		$list = ModeratedPosts::list($topicIds);
		$this->db->execute('DELETE FROM '.$this->db->table('topics').' WHERE id IN('.$list.') OR moved_to IN('.$list.')');
	}

	public function removeSubscriptions(int ...$topicIds): void {
		if ($topicIds !== array())
			$this->db->execute('DELETE FROM '.$this->db->table('subscriptions').' WHERE topic_id IN('.ModeratedPosts::list($topicIds).')');
	}

	public function postIds(int ...$topicIds): array {
		if ($topicIds === array())
			return array();

		return array_map(static fn (Row $row): int => $row->int('id'),
			$this->db->select('SELECT p.id FROM '.$this->db->table('posts').' AS p WHERE p.topic_id IN('.ModeratedPosts::list($topicIds).')'));
	}

	public function removePosts(int ...$topicIds): void {
		if ($topicIds !== array())
			$this->db->execute('DELETE FROM '.$this->db->table('posts').' WHERE topic_id IN('.ModeratedPosts::list($topicIds).')');
	}

	public function closeTopics(bool $closed, int $forumId, int ...$topicIds): void {
		if ($topicIds !== array())
			$this->db->execute('UPDATE '.$this->db->table('topics').' SET closed=? WHERE id IN('.ModeratedPosts::list($topicIds).') AND forum_id=?', $closed ? 1 : 0, $forumId);
	}

	public function stickTopics(bool $sticky, int $forumId, int ...$topicIds): void {
		if ($topicIds !== array())
			$this->db->execute('UPDATE '.$this->db->table('topics').' SET sticky=? WHERE id IN('.ModeratedPosts::list($topicIds).') AND forum_id=?', $sticky ? 1 : 0, $forumId);
	}

	private static function text(int|float|string|null $value): ?string {
		return $value !== null ? (string) $value : null;
	}
}
