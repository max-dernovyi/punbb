<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Moderate\Api\Data\FirstPostInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedPostInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedTopicInterface;
use PunBB\Module\Moderate\Api\Data\NewTopicInterface;
use PunBB\Module\Moderate\Api\ModeratedPostsInterface;
use PunBB\Module\Moderate\Model\FirstPost;
use PunBB\Module\Moderate\Model\ModeratedTopic;

/**
 * The query points of moderating a topic's posts, with the query arrays
 * moderate.php built. A query a point changed answers instead, its rows kept
 * with any column it added; a statement a point changed runs instead, and the
 * repository is handed nothing to store. What was read is left where the page
 * script kept it: the topic in $tid and $cur_topic, the first post split off
 * in $first_post_data, and the new topic's subject and id in $new_subject and $new_tid.
 */
final class ModeratedPostsPlugin {
	public const CHECK_REPLIES = 'replies';

	public function __construct(private readonly PluggedQuery $queries, private readonly ModerationRows $rows) {}

	public function afterPosterAddress(ModeratedPostsInterface $subject, ?string $result, int $postId): ?string {
		$GLOBALS['get_host'] = $postId;

		$query = array(
			'SELECT'	=> 'p.poster_ip',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.id='.$postId
		);

		if ($this->queries->changed('mr_view_ip_qr_get_poster_ip', ModeratedPostsInterface::class.'::posterAddress', $query))
		{
			$value = PluggedQuery::value($query);
			$result = $value !== null && $value !== false ? Markers::markup($value) : null;
		}

		return $result;
	}

	public function afterTopic(ModeratedPostsInterface $subject, ?ModeratedTopicInterface $result, int $id, int $forumId): ?ModeratedTopicInterface {
		$GLOBALS['tid'] = $id;

		$query = array(
			'SELECT'	=> 't.subject, t.poster, t.first_post_id, t.posted, t.num_replies',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.id='.$id.' AND t.forum_id='.$forumId.' AND t.moved_to IS NULL'
		);

		if ($this->queries->changed('mr_post_actions_qr_get_topic_info', ModeratedPostsInterface::class.'::topic', $query))
		{
			$result = null;
			foreach (array_slice(PluggedQuery::rows($query), 0, 1) as $row)
			{
				$result = new ModeratedTopic($id, Markers::markup($row['subject'] ?? ''), Markers::markup($row['poster'] ?? ''), (int) Markers::markup($row['first_post_id'] ?? 0),
					(int) Markers::markup($row['posted'] ?? 0), (int) Markers::markup($row['num_replies'] ?? 0));
				$this->rows->keep($result, $row);
			}
		}

		$GLOBALS['cur_topic'] = $result !== null ? $this->rows->topic($result) : false;

		return $result;
	}

	public function afterCountReplies(ModeratedPostsInterface $subject, int $result, int $topicId, int $firstPostId, int ...$postIds): int {
		$point = $this->rows->point(self::CHECK_REPLIES);
		if ($point === '')
			return $result;

		$query = array(
			'SELECT'	=> 'COUNT(p.id)',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.id IN('.implode(',', $postIds).') AND p.id!='.$firstPostId.' AND p.topic_id='.$topicId
		);

		if ($this->queries->changed($point, ModeratedPostsInterface::class.'::countReplies', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query));

		return $result;
	}

	/** @return list<int>|null */
	public function beforeDeletePosts(ModeratedPostsInterface $subject, int ...$postIds): ?array {
		$query = array(
			'DELETE'	=> 'posts',
			'WHERE'		=> 'id IN('.implode(',', $postIds).')'
		);

		if (!$this->queries->changed('mr_confirm_delete_posts_qr_delete_posts', ModeratedPostsInterface::class.'::deletePosts', $query))
			return null;

		PluggedQuery::run($query);

		return array();
	}

	public function afterFirstPost(ModeratedPostsInterface $subject, ?FirstPostInterface $result, int $id): ?FirstPostInterface {
		$query = array(
			'SELECT'	=> 'p.id, p.poster, p.posted',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.id = '.$id
		);

		$row = null;
		if ($this->queries->changed('mr_confirm_split_posts_qr_get_first_post_data', ModeratedPostsInterface::class.'::firstPost', $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$result = $row !== null ? new FirstPost((int) Markers::markup($row['id'] ?? 0), Markers::markup($row['poster'] ?? ''), (int) Markers::markup($row['posted'] ?? 0)) : null;
		}

		$GLOBALS['first_post_data'] = $row ?? ($result !== null ? array('id' => $result->id(), 'poster' => $result->poster(), 'posted' => $result->posted()) : false);

		return $result;
	}

	/** @return list<NewTopicInterface>|null */
	public function beforeAddTopics(ModeratedPostsInterface $subject, NewTopicInterface ...$topics): ?array {
		$kept = array();
		foreach ($topics as $topic)
		{
			$GLOBALS['new_subject'] = $topic->subject();

			$query = array(
				'INSERT'	=> 'poster, subject, posted, first_post_id, forum_id',
				'INTO'		=> 'topics',
				'VALUES'	=> '\''.self::escape($topic->poster()).'\', \''.self::escape($topic->subject()).'\', '.$topic->posted().', '.$topic->firstPostId().', '.$topic->forumId()
			);

			if ($this->queries->changed('mr_confirm_split_posts_qr_add_topic', ModeratedPostsInterface::class.'::addTopics', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $topic;
		}

		return count($kept) !== count($topics) ? $kept : null;
	}

	public function afterLastTopicId(ModeratedPostsInterface $subject, int $result): int {
		$GLOBALS['new_tid'] = $result;

		return $result;
	}

	/** @return list<int>|null */
	public function beforeMovePosts(ModeratedPostsInterface $subject, int $topicId, int ...$postIds): ?array {
		$query = array(
			'UPDATE'	=> 'posts',
			'SET'		=> 'topic_id='.$topicId,
			'WHERE'		=> 'id IN('.implode(',', $postIds).')'
		);

		if (!$this->queries->changed('mr_confirm_split_posts_qr_move_posts', ModeratedPostsInterface::class.'::movePosts', $query))
			return null;

		PluggedQuery::run($query);

		return array($topicId);
	}

	/**
	 * @param list<ModeratedPostInterface> $result
	 * @return list<ModeratedPostInterface>
	 */
	public function afterPosts(ModeratedPostsInterface $subject, array $result, int $topicId, int $offset, int $limit): array {
		$query = array(
			'SELECT'	=> 'u.title, u.num_posts, g.g_id, g.g_user_title, p.id, p.poster, p.poster_id, p.message, p.hide_smilies, p.posted, p.edited, p.edited_by',
			'FROM'		=> 'posts AS p',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'users AS u',
					'ON'			=> 'u.id=p.poster_id'
				),
				array(
					'INNER JOIN'	=> 'groups AS g',
					'ON'			=> 'g.g_id=u.group_id'
				)
			),
			'WHERE'		=> 'p.topic_id='.$topicId,
			'ORDER BY'	=> 'p.id',
			'LIMIT'		=> $offset.','.$limit
		);

		if ($this->queries->changed('mr_post_actions_qr_get_posts', ModeratedPostsInterface::class.'::posts', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$post = ModerationRows::postOf($row);
				$this->rows->keep($post, $row);
				$result[] = $post;
			}
		}

		return $result;
	}

	private static function escape(string $text): string {
		return Markers::markup(LegacyConnection::legacy()->escape($text));
	}
}
