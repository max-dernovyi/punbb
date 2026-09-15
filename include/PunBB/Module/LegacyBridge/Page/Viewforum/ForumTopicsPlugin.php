<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewforum;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Viewforum\Api\Data\ListedTopicInterface;
use PunBB\Module\Viewforum\Api\Data\ViewedForumInterface;
use PunBB\Module\Viewforum\Api\ForumTopicsInterface;

/**
 * A forum page's query points, with the query arrays viewforum.php built. The
 * forum is left in $id and $cur_forum, the page's topics in $topics_id and
 * $topics, as the page script kept them.
 */
final class ForumTopicsPlugin {
	private const TOPIC_COLUMNS = 't.id, t.poster, t.subject, t.posted, t.first_post_id, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to';

	public function __construct(private readonly PluggedQuery $queries, private readonly TopicRows $rows) {}

	public function afterForum(ForumTopicsInterface $subject, ?ViewedForumInterface $result, int $forumId, int $groupId, ?int $subscriberId): ?ViewedForumInterface {
		$GLOBALS['id'] = $forumId;

		$query = array(
			'SELECT'	=> 'f.forum_name, f.redirect_url, f.moderators, f.num_topics, f.sort_by, fp.post_topics, f.forum_desc',
			'FROM'		=> 'forums AS f',
			'JOINS'		=> array(
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$forumId
		);

		if ($subscriberId !== null)
		{
			$query['SELECT'] .= ', fs.user_id AS is_subscribed';
			$query['JOINS'][] = array(
				'LEFT JOIN'	=> 'forum_subscriptions AS fs',
				'ON'		=> '(f.id=fs.forum_id AND fs.user_id='.$subscriberId.')'
			);
		}

		if ($this->queries->changed('vf_qr_get_forum_info', ForumTopicsInterface::class.'::forum', $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$result = null;

			if ($row !== null)
			{
				$result = TopicRows::forumOf($forumId, $row);
				$this->rows->keep($result, $row);
			}
		}

		if ($result === null)
		{
			$GLOBALS['cur_forum'] = false;

			return null;
		}

		$forum = $this->rows->forum($result);
		if ($subscriberId !== null && !array_key_exists('is_subscribed', $forum))
			$forum['is_subscribed'] = $result->isSubscribed() ? $subscriberId : null;

		$GLOBALS['cur_forum'] = $forum;

		return $result;
	}

	/**
	 * @param list<int> $result
	 * @return list<int>
	 */
	public function afterTopicIds(ForumTopicsInterface $subject, array $result, int $forumId, bool $byPosted, int $offset, int $limit): array {
		$query = array(
			'SELECT'	=> 't.id',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.forum_id='.$forumId,
			'ORDER BY'	=> 't.sticky DESC, '.($byPosted ? 't.posted' : 't.last_post').' DESC',
			'LIMIT'		=> $offset.', '.$limit
		);

		if ($this->queries->changed('vt_qr_get_topics_id', ForumTopicsInterface::class.'::topicIds', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
				$result[] = (int) Markers::markup($row['id'] ?? 0);
		}

		$GLOBALS['topics_id'] = $result;

		return $result;
	}

	/**
	 * @param list<int> $topicIds
	 * @param list<ListedTopicInterface> $result
	 * @return list<ListedTopicInterface>
	 */
	public function afterTopics(ForumTopicsInterface $subject, array $result, array $topicIds, bool $byPosted, ?int $posterId): array {
		$query = array(
			'SELECT'	=> self::TOPIC_COLUMNS,
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.id IN ('.implode(',', $topicIds).')',
			'ORDER BY'	=> 't.sticky DESC, '.($byPosted ? 't.posted' : 't.last_post').' DESC',
		);

		$changed = false;
		if ($posterId !== null)
		{
			$query = array(
				'SELECT'	=> self::TOPIC_COLUMNS.', p.poster_id AS has_posted',
				'FROM'		=> $query['FROM'],
				'WHERE'		=> $query['WHERE'],
				'ORDER BY'	=> $query['ORDER BY'],
				'JOINS'		=> array(
					array(
						'LEFT JOIN'		=> 'posts AS p',
						'ON'			=> '(p.poster_id='.$posterId.' AND p.topic_id=t.id)'
					)
				),
				'GROUP BY'	=> self::TOPIC_COLUMNS.', p.poster_id',
			);

			$changed = $this->queries->changed('vf_qr_get_has_posted', ForumTopicsInterface::class.'::topics', $query);
		}

		if ($this->queries->changed('vf_qr_get_topics', ForumTopicsInterface::class.'::topics', $query) || $changed)
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$topic = TopicRows::topicOf($row, $posterId);
				$this->rows->keep($topic, $row);
				$result[] = $topic;
			}
		}

		$GLOBALS['topics'] = array_map(fn (ListedTopicInterface $topic): array => $this->rows->topic($topic, $posterId), $result);

		return $result;
	}
}
