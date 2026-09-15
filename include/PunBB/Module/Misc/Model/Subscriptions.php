<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Misc\Api\Data\SubscriptionInterface;
use PunBB\Module\Misc\Api\SubscriptionsInterface;

/**
 * The subscriptions and forum subscriptions tables, with the topics and forums they name.
 */
final class Subscriptions implements SubscriptionsInterface {
	public function __construct(private readonly Connection $db) {}

	public function topicSubject(int $topicId, int $groupId): ?string {
		return self::text($this->db->selectValue('SELECT t.subject FROM '.$this->db->table('topics').' AS t'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=t.forum_id AND fp.group_id=?)'.
			' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.id=? AND t.moved_to IS NULL', $groupId, $topicId));
	}

	public function isSubscribedToTopic(int $userId, int $topicId): bool {
		return (int) $this->db->selectValue('SELECT COUNT(s.user_id) FROM '.$this->db->table('subscriptions').' AS s WHERE s.user_id=? AND s.topic_id=?', $userId, $topicId) > 0;
	}

	public function subscribeToTopic(SubscriptionInterface ...$subscriptions): void {
		foreach ($subscriptions as $subscription)
			$this->db->execute('INSERT INTO '.$this->db->table('subscriptions').' (user_id, topic_id) VALUES (?, ?)', $subscription->userId(), $subscription->targetId());
	}

	public function subscribedTopicSubject(int $userId, int $topicId): ?string {
		return self::text($this->db->selectValue('SELECT t.subject FROM '.$this->db->table('topics').' AS t'.
			' INNER JOIN '.$this->db->table('subscriptions').' AS s ON s.user_id=? AND s.topic_id=t.id WHERE t.id=?', $userId, $topicId));
	}

	public function unsubscribeFromTopic(SubscriptionInterface ...$subscriptions): void {
		foreach ($subscriptions as $subscription)
			$this->db->execute('DELETE FROM '.$this->db->table('subscriptions').' WHERE user_id=? AND topic_id=?', $subscription->userId(), $subscription->targetId());
	}

	public function forumName(int $forumId, int $groupId): ?string {
		return $this->readableForumName($forumId, $groupId);
	}

	public function isSubscribedToForum(int $userId, int $forumId): bool {
		return (int) $this->db->selectValue('SELECT COUNT(fs.user_id) FROM '.$this->db->table('forum_subscriptions').' AS fs WHERE fs.user_id=? AND fs.forum_id=?', $userId, $forumId) > 0;
	}

	public function subscribeToForum(SubscriptionInterface ...$subscriptions): void {
		foreach ($subscriptions as $subscription)
			$this->db->execute('INSERT INTO '.$this->db->table('forum_subscriptions').' (user_id, forum_id) VALUES (?, ?)', $subscription->userId(), $subscription->targetId());
	}

	public function unsubscribingForumName(int $forumId, int $groupId): ?string {
		return $this->readableForumName($forumId, $groupId);
	}

	public function unsubscribeFromForum(SubscriptionInterface ...$subscriptions): void {
		foreach ($subscriptions as $subscription)
			$this->db->execute('DELETE FROM '.$this->db->table('forum_subscriptions').' WHERE user_id=? AND forum_id=?', $subscription->userId(), $subscription->targetId());
	}

	private function readableForumName(int $forumId, int $groupId): ?string {
		return self::text($this->db->selectValue('SELECT f.forum_name FROM '.$this->db->table('forums').' AS f'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)'.
			' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id=?', $groupId, $forumId));
	}

	private static function text(int|float|string|null $value): ?string {
		return $value !== null ? (string) $value : null;
	}
}
