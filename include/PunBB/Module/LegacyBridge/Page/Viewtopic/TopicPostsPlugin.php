<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewtopic;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Viewtopic\Api\Data\PostLocationInterface;
use PunBB\Module\Viewtopic\Api\Data\TopicPostInterface;
use PunBB\Module\Viewtopic\Api\Data\ViewedTopicInterface;
use PunBB\Module\Viewtopic\Api\TopicPostsInterface;
use PunBB\Module\Viewtopic\Model\PostLocation;

/**
 * A topic page's query points, with the query arrays viewtopic.php built. What
 * each answered is left in the variables the page script kept it in: $id and
 * $cur_topic, $topic_info and $num_posts, $first_new_post_id, $last_post_id,
 * $posts_id. A statement a point changed runs instead, and the repository is
 * handed no topic to count a view of.
 */
final class TopicPostsPlugin {
	private const POST_COLUMNS = 'u.email, u.title, u.url, u.location, u.signature, u.email_setting, u.num_posts, u.registered, u.admin_note, u.avatar, u.avatar_width, u.avatar_height, p.id, p.poster AS username, p.poster_id, p.poster_ip, p.poster_email, p.message, p.hide_smilies, p.posted, p.edited, p.edited_by, g.g_id, g.g_user_title, o.user_id AS is_online';

	public function __construct(private readonly PluggedQuery $queries, private readonly ViewedTopicRows $rows) {}

	public function afterLocate(TopicPostsInterface $subject, ?PostLocationInterface $result, int $postId): ?PostLocationInterface {
		$query = array(
			'SELECT'	=> 'p.topic_id, p.posted',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.id='.$postId
		);

		if ($this->queries->changed('vt_qr_get_post_info', TopicPostsInterface::class.'::locate', $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$result = $row !== null ? new PostLocation((int) Markers::markup($row['topic_id'] ?? 0), (int) Markers::markup($row['posted'] ?? 0)) : null;
		}

		$GLOBALS['topic_info'] = $result !== null ? array('topic_id' => $result->topicId(), 'posted' => $result->posted()) : false;
		if ($result !== null)
			$GLOBALS['id'] = $result->topicId();

		return $result;
	}

	public function afterCountBefore(TopicPostsInterface $subject, int $result, int $topicId, int $posted): int {
		$query = array(
			'SELECT'	=> 'COUNT(p.id)',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.topic_id='.$topicId.' AND p.posted<'.$posted
		);

		if ($this->queries->changed('vt_qr_get_post_page', TopicPostsInterface::class.'::countBefore', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query));

		$GLOBALS['num_posts'] = $result + 1;

		return $result;
	}

	public function afterFirstPostAfter(TopicPostsInterface $subject, ?int $result, int $topicId, int $after): ?int {
		$query = array(
			'SELECT'	=> 'MIN(p.id)',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.topic_id='.$topicId.' AND p.posted>'.$after
		);

		if ($this->queries->changed('vt_qr_get_first_new_post', TopicPostsInterface::class.'::firstPostAfter', $query))
		{
			$value = PluggedQuery::value($query);
			$result = $value !== null && $value !== false ? (int) Markers::markup($value) : null;
		}

		$GLOBALS['first_new_post_id'] = $result;

		return $result;
	}

	public function afterLastPostId(TopicPostsInterface $subject, ?int $result, int $topicId): ?int {
		$query = array(
			'SELECT'	=> 't.last_post_id',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.id='.$topicId
		);

		if ($this->queries->changed('vt_qr_get_last_post', TopicPostsInterface::class.'::lastPostId', $query))
		{
			$value = PluggedQuery::value($query);
			$result = $value !== null && $value !== false ? (int) Markers::markup($value) : null;
		}

		$GLOBALS['last_post_id'] = $result;

		return $result;
	}

	public function afterTopic(TopicPostsInterface $subject, ?ViewedTopicInterface $result, int $topicId, int $groupId, ?int $subscriberId): ?ViewedTopicInterface {
		$GLOBALS['id'] = $topicId;

		$query = array(
			'SELECT'	=> 't.subject, t.first_post_id, t.closed, t.num_replies, t.sticky, f.id AS forum_id, f.forum_name, f.moderators, fp.post_replies',
			'FROM'		=> 'topics AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'f.id=t.forum_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$topicId.' AND t.moved_to IS NULL'
		);

		if ($subscriberId !== null)
		{
			$query['SELECT'] .= ', s.user_id AS is_subscribed';
			$query['JOINS'][] = array(
				'LEFT JOIN'	=> 'subscriptions AS s',
				'ON'		=> '(t.id=s.topic_id AND s.user_id='.$subscriberId.')'
			);
		}

		if ($this->queries->changed('vt_qr_get_topic_info', TopicPostsInterface::class.'::topic', $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$result = null;

			if ($row !== null)
			{
				$result = ViewedTopicRows::topicOf($topicId, $row);
				$this->rows->keep($result, $row);
			}
		}

		$GLOBALS['cur_topic'] = $result !== null ? $this->rows->topic($result, $subscriberId) : false;

		return $result;
	}

	/**
	 * @param list<int> $result
	 * @return list<int>
	 */
	public function afterPostIds(TopicPostsInterface $subject, array $result, int $topicId, int $offset, int $limit): array {
		$query = array(
			'SELECT'	=> 'p.id',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.topic_id='.$topicId,
			'ORDER BY'	=> 'p.id',
			'LIMIT'		=> $offset.','.$limit
		);

		if ($this->queries->changed('vt_qr_get_posts_id', TopicPostsInterface::class.'::postIds', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
				$result[] = (int) Markers::markup($row['id'] ?? 0);
		}

		$GLOBALS['posts_id'] = $result;

		return $result;
	}

	/**
	 * @param list<TopicPostInterface> $result
	 * @param list<int> $postIds
	 * @return list<TopicPostInterface>
	 */
	public function afterPosts(TopicPostsInterface $subject, array $result, array $postIds): array {
		$query = array(
			'SELECT'	=> self::POST_COLUMNS,
			'FROM'		=> 'posts AS p',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'users AS u',
					'ON'			=> 'u.id=p.poster_id'
				),
				array(
					'INNER JOIN'	=> 'groups AS g',
					'ON'			=> 'g.g_id=u.group_id'
				),
				array(
					'LEFT JOIN'		=> 'online AS o',
					'ON'			=> '(o.user_id=u.id AND o.user_id!=1 AND o.idle=0)'
				),
			),
			'WHERE'		=> 'p.id IN ('.implode(',', $postIds).')',
			'ORDER BY'	=> 'p.id'
		);

		if ($this->queries->changed('vt_qr_get_posts', TopicPostsInterface::class.'::posts', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$post = ViewedTopicRows::postOf($row);
				$this->rows->keep($post, $row);
				$result[] = $post;
			}
		}

		$GLOBALS['user_data_cache'] = array();

		return $result;
	}

	/** @return list<int>|null */
	public function beforeCountView(TopicPostsInterface $subject, int ...$topicIds): ?array {
		$kept = array();
		foreach ($topicIds as $topicId)
		{
			$query = array(
				'UPDATE'	=> 'topics',
				'SET'		=> 'num_views=num_views+1',
				'WHERE'		=> 'id='.$topicId,
			);

			if ($this->queries->changed('vt_qr_increment_num_views', TopicPostsInterface::class.'::countView', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $topicId;
		}

		return count($kept) !== count($topicIds) ? $kept : null;
	}
}
