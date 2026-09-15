<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Misc;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\FoundName;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Misc\Api\Data\SubscriptionInterface;
use PunBB\Module\Misc\Api\SubscriptionsInterface;

/**
 * The query points of subscribing and unsubscribing, with the query arrays
 * misc.php built. A query a point changed answers instead; a statement a point
 * changed runs instead, and the repository is handed nothing to store. The
 * topic's subject is left in $subject, the forum's name in $forum_name.
 */
final class SubscriptionsPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	public function afterTopicSubject(SubscriptionsInterface $subject, ?string $result, int $topicId, int $groupId): ?string {
		$query = array(
			'SELECT'	=> 'subject',
			'FROM'		=> 'topics AS t',
			'JOINS'		=> array(
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=t.forum_id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$topicId.' AND t.moved_to IS NULL'
		);

		if ($this->queries->changed('mi_subscribe_qr_topic_exists', SubscriptionsInterface::class.'::topicSubject', $query))
			$result = FoundName::of($query);

		$GLOBALS['subject'] = $result;

		return $result;
	}

	public function afterIsSubscribedToTopic(SubscriptionsInterface $subject, bool $result, int $userId, int $topicId): bool {
		$query = array(
			'SELECT'	=> 'COUNT(s.user_id)',
			'FROM'		=> 'subscriptions AS s',
			'WHERE'		=> 'user_id='.$userId.' AND topic_id='.$topicId
		);

		if ($this->queries->changed('mi_subscribe_qr_check_subscribed', SubscriptionsInterface::class.'::isSubscribedToTopic', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query)) > 0;

		return $result;
	}

	/** @return list<SubscriptionInterface>|null */
	public function beforeSubscribeToTopic(SubscriptionsInterface $subject, SubscriptionInterface ...$subscriptions): ?array {
		return $this->statements($subscriptions, 'mi_subscribe_add_subscription', 'subscribeToTopic', static fn (SubscriptionInterface $subscription): array => array(
			'INSERT'	=> 'user_id, topic_id',
			'INTO'		=> 'subscriptions',
			'VALUES'	=> $subscription->userId().' ,'.$subscription->targetId()
		));
	}

	public function afterSubscribedTopicSubject(SubscriptionsInterface $subject, ?string $result, int $userId, int $topicId): ?string {
		$query = array(
			'SELECT'	=> 't.subject',
			'FROM'		=> 'topics AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'subscriptions AS s',
					'ON'			=> 's.user_id='.$userId.' AND s.topic_id=t.id'
				)
			),
			'WHERE'		=> 't.id='.$topicId
		);

		if ($this->queries->changed('mi_unsubscribe_qr_check_subscribed', SubscriptionsInterface::class.'::subscribedTopicSubject', $query))
			$result = FoundName::of($query);

		$GLOBALS['subject'] = $result;

		return $result;
	}

	/** @return list<SubscriptionInterface>|null */
	public function beforeUnsubscribeFromTopic(SubscriptionsInterface $subject, SubscriptionInterface ...$subscriptions): ?array {
		return $this->statements($subscriptions, 'mi_unsubscribe_qr_delete_subscription', 'unsubscribeFromTopic', static fn (SubscriptionInterface $subscription): array => array(
			'DELETE'	=> 'subscriptions',
			'WHERE'		=> 'user_id='.$subscription->userId().' AND topic_id='.$subscription->targetId()
		));
	}

	public function afterForumName(SubscriptionsInterface $subject, ?string $result, int $forumId, int $groupId): ?string {
		$query = self::readableForum($forumId, $groupId);

		if ($this->queries->changed('mi_forum_subscribe_qr_forum_exists', SubscriptionsInterface::class.'::forumName', $query))
			$result = FoundName::of($query);

		$GLOBALS['forum_name'] = $result;

		return $result;
	}

	public function afterIsSubscribedToForum(SubscriptionsInterface $subject, bool $result, int $userId, int $forumId): bool {
		$query = array(
			'SELECT'	=> 'COUNT(fs.user_id)',
			'FROM'		=> 'forum_subscriptions AS fs',
			'WHERE'		=> 'user_id='.$userId.' AND forum_id='.$forumId
		);

		if ($this->queries->changed('mi_forum_subscribe_qr_check_subscribed', SubscriptionsInterface::class.'::isSubscribedToForum', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query)) > 0;

		return $result;
	}

	/** @return list<SubscriptionInterface>|null */
	public function beforeSubscribeToForum(SubscriptionsInterface $subject, SubscriptionInterface ...$subscriptions): ?array {
		return $this->statements($subscriptions, 'mi_forum_subscribe_add_subscription', 'subscribeToForum', static fn (SubscriptionInterface $subscription): array => array(
			'INSERT'	=> 'user_id, forum_id',
			'INTO'		=> 'forum_subscriptions',
			'VALUES'	=> $subscription->userId().' ,'.$subscription->targetId()
		));
	}

	public function afterUnsubscribingForumName(SubscriptionsInterface $subject, ?string $result, int $forumId, int $groupId): ?string {
		$query = self::readableForum($forumId, $groupId);

		if ($this->queries->changed('mi_forum_unsubscribe_qr_check_subscribed', SubscriptionsInterface::class.'::unsubscribingForumName', $query))
			$result = FoundName::of($query);

		$GLOBALS['forum_name'] = $result;

		return $result;
	}

	/** @return list<SubscriptionInterface>|null */
	public function beforeUnsubscribeFromForum(SubscriptionsInterface $subject, SubscriptionInterface ...$subscriptions): ?array {
		return $this->statements($subscriptions, 'mi_unsubscribe_qr_delete_subscription', 'unsubscribeFromForum', static fn (SubscriptionInterface $subscription): array => array(
			'DELETE'	=> 'forum_subscriptions',
			'WHERE'		=> 'user_id='.$subscription->userId().' AND forum_id='.$subscription->targetId()
		));
	}

	/**
	 * Runs the statement $point changed for each subscription, and hands the repository the rest.
	 *
	 * @param array<SubscriptionInterface> $subscriptions
	 * @param \Closure(SubscriptionInterface): array<string, mixed> $statement
	 * @return list<SubscriptionInterface>|null
	 */
	private function statements(array $subscriptions, string $point, string $method, \Closure $statement): ?array {
		$kept = array();
		foreach ($subscriptions as $subscription)
		{
			$query = $statement($subscription);

			if ($this->queries->changed($point, SubscriptionsInterface::class.'::'.$method, $query))
				PluggedQuery::run($query);
			else
				$kept[] = $subscription;
		}

		return count($kept) !== count($subscriptions) ? $kept : null;
	}

	/** @return array<string, mixed> */
	private static function readableForum(int $forumId, int $groupId): array {
		return array(
			'SELECT'	=> 'f.forum_name',
			'FROM'		=> 'forums AS f',
			'JOINS'		=> array(
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$forumId
		);
	}
}
