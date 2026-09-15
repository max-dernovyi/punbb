<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Moderate\Api\Data\ListedTopicInterface;
use PunBB\Module\Moderate\Api\Data\MergeTargetInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedForumInterface;
use PunBB\Module\Moderate\Api\Data\MovedTopicInterface;
use PunBB\Module\Moderate\Api\Data\RedirectTopicInterface;
use PunBB\Module\Moderate\Api\Data\TargetForumInterface;
use PunBB\Module\Moderate\Api\ModeratedTopicsInterface;
use PunBB\Module\Moderate\Model\MergeTarget;
use PunBB\Module\Moderate\Model\MovedTopic;
use PunBB\Module\Moderate\Model\TargetForum;

/**
 * The query points of moderating a forum's topics, with the query arrays
 * moderate.php built. A query a point changed answers instead, its rows kept
 * with any column it added; a statement a point changed runs instead, and the
 * repository is handed nothing to store. What was read is left where the page
 * script kept it: the forum in $fid and $cur_forum, the forums to move to in
 * $forum_list, the forum moved to in $move_to_forum_name, a topic moved in
 * $moved_to, the merge in $num_topics and $merge_to_tid, the forums synced in
 * $forum_ids and the posts deleted in $post_ids.
 */
final class ModeratedTopicsPlugin {
	public const CHECK_TOPICS = 'topics';

	public const CHECK_SUBJECT = 'subject';

	public const CHECK_CLOSE = 'close';

	/** The point a link's subject is read at => the variable the page script held its topic in */
	private const SUBJECT_TOPICS = array(
		'mr_open_close_single_topic_qr_get_subject'	=> 'topic_id',
		'mr_stick_topic_qr_get_subject'				=> 'stick',
		'mr_unstick_topic_qr_get_subject'			=> 'unstick',
	);

	private const TOPIC_COLUMNS = 't.id, t.poster, t.subject, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to';

	public function __construct(private readonly PluggedQuery $queries, private readonly ModerationRows $rows) {}

	public function afterForum(ModeratedTopicsInterface $subject, ?ModeratedForumInterface $result, int $id, int $groupId): ?ModeratedForumInterface {
		$GLOBALS['fid'] = $id;

		$query = array(
			'SELECT'	=> 'f.forum_name, f.redirect_url, f.num_topics, f.moderators, f.sort_by',
			'FROM'		=> 'forums AS f',
			'JOINS'		=> array(
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$id
		);

		if ($this->queries->changed('mr_qr_get_forum_data', ModeratedTopicsInterface::class.'::forum', $query))
		{
			$result = null;
			foreach (array_slice(PluggedQuery::rows($query), 0, 1) as $row)
			{
				$result = ModerationRows::forumOf($id, $row);
				$this->rows->keep($result, $row);
			}
		}

		$GLOBALS['cur_forum'] = $result !== null ? $this->rows->forum($result) : false;

		return $result;
	}

	/**
	 * @param list<ListedTopicInterface> $result
	 * @return list<ListedTopicInterface>
	 */
	public function afterTopics(ModeratedTopicsInterface $subject, array $result, int $forumId, bool $byPosted, int $offset, int $limit, ?int $postedBy): array {
		$query = array(
			'SELECT'	=> self::TOPIC_COLUMNS,
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 'forum_id='.$forumId,
			'ORDER BY'	=> 't.sticky DESC, '.($byPosted ? 't.posted' : 't.last_post').' DESC',
			'LIMIT'		=>	$offset.', '.$limit
		);

		ForumPage::set('start_from', $offset);

		$changed = false;
		if ($postedBy !== null)
		{
			$query = array(
				'SELECT'	=> self::TOPIC_COLUMNS.', p.poster_id AS has_posted',
				'FROM'		=> $query['FROM'],
				'WHERE'		=> $query['WHERE'],
				'ORDER BY'	=> $query['ORDER BY'],
				'LIMIT'		=> $query['LIMIT'],
				'JOINS'		=> array(
					array(
						'LEFT JOIN'		=> 'posts AS p',
						'ON'			=> '(p.poster_id='.$postedBy.' AND p.topic_id=t.id)'
					)
				),
				'GROUP BY'	=> self::TOPIC_COLUMNS.', p.poster_id',
			);

			$changed = $this->queries->changed('mr_qr_get_has_posted', ModeratedTopicsInterface::class.'::topics', $query);
		}

		if ($this->queries->changed('mr_qr_get_topics', ModeratedTopicsInterface::class.'::topics', $query) || $changed)
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$topic = ModerationRows::listedOf($row, $postedBy);
				$this->rows->keep($topic, $row);
				$result[] = $topic;
			}
		}

		return $result;
	}

	public function afterSubject(ModeratedTopicsInterface $subject, ?string $result, int $id): ?string {
		$query = array(
			'SELECT'	=> 't.subject',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.id='.$id
		);

		if ($this->queries->changed('mr_move_topics_qr_get_topic_to_move_subject', ModeratedTopicsInterface::class.'::subject', $query))
			$result = self::text(PluggedQuery::value($query));

		$GLOBALS['subject'] = $result ?? false;

		return $result;
	}

	public function afterSubjectIn(ModeratedTopicsInterface $subject, ?string $result, int $id, int $forumId): ?string {
		$point = $this->rows->point(self::CHECK_SUBJECT);
		if ($point === '')
			return $result;

		$query = array(
			'SELECT'	=> 't.subject',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.id='.$id.' AND forum_id='.$forumId
		);

		$GLOBALS[self::SUBJECT_TOPICS[$point] ?? 'topic_id'] = $id;

		if ($this->queries->changed($point, ModeratedTopicsInterface::class.'::subjectIn', $query))
			$result = self::text(PluggedQuery::value($query));

		$GLOBALS['subject'] = $result ?? false;

		return $result;
	}

	/**
	 * @param list<TargetForumInterface> $result
	 * @return list<TargetForumInterface>
	 */
	public function afterMoveTargets(ModeratedTopicsInterface $subject, array $result, int $exceptId, int $groupId): array {
		$query = array(
			'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name',
			'FROM'		=> 'categories AS c',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'c.id=f.cat_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.redirect_url IS NULL AND f.id!='.$exceptId,
			'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
		);

		if ($this->queries->changed('mr_move_topics_qr_get_target_forums', ModeratedTopicsInterface::class.'::moveTargets', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$forum = new TargetForum((int) Markers::markup($row['cid'] ?? 0), Markers::markup($row['cat_name'] ?? ''), (int) Markers::markup($row['fid'] ?? 0), Markers::markup($row['forum_name'] ?? ''));
				$this->rows->keep($forum, $row);
				$result[] = $forum;
			}
		}

		$GLOBALS['forum_list'] = array_map($this->rows->target(...), $result);

		return $result;
	}

	public function afterForumName(ModeratedTopicsInterface $subject, ?string $result, int $id): ?string {
		$GLOBALS['move_to_forum'] = $id;

		$query = array(
			'SELECT'	=> 'f.forum_name',
			'FROM'		=> 'forums AS f',
			'WHERE'		=> 'f.id='.$id
		);

		if ($this->queries->changed('mr_confirm_move_topics_qr_get_move_to_forum_name', ModeratedTopicsInterface::class.'::forumName', $query))
			$result = self::text(PluggedQuery::value($query));

		$GLOBALS['move_to_forum_name'] = $result ?? false;

		return $result;
	}

	public function afterCountTopics(ModeratedTopicsInterface $subject, int $result, int $forumId, int ...$topicIds): int {
		$GLOBALS['topics'] = $topicIds;

		$point = $this->rows->point(self::CHECK_TOPICS);
		if ($point === '')
			return $result;

		$query = array(
			'SELECT'	=> 'COUNT(t.id)',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.id IN('.implode(',', $topicIds).') AND t.forum_id='.$forumId
		);

		if ($this->queries->changed($point, ModeratedTopicsInterface::class.'::countTopics', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query));

		return $result;
	}

	/** @return list<int>|null */
	public function beforeRemoveRedirects(ModeratedTopicsInterface $subject, int $forumId, int ...$topicIds): ?array {
		return $this->statement('mr_confirm_move_topics_qr_delete_redirect_topics', 'removeRedirects', array(
			'DELETE'	=> 'topics',
			'WHERE'		=> 'forum_id='.$forumId.' AND moved_to IN('.implode(',', $topicIds).')'
		), array($forumId));
	}

	/** @return list<int>|null */
	public function beforeMoveTopics(ModeratedTopicsInterface $subject, int $forumId, int ...$topicIds): ?array {
		return $this->statement('mr_confirm_move_topics_qr_move_topics', 'moveTopics', array(
			'UPDATE'	=> 'topics',
			'SET'		=> 'forum_id='.$forumId,
			'WHERE'		=> 'id IN('.implode(',', $topicIds).')'
		), array($forumId));
	}

	public function afterMovedTopic(ModeratedTopicsInterface $subject, ?MovedTopicInterface $result, int $id): ?MovedTopicInterface {
		$GLOBALS['cur_topic'] = $id;

		$query = array(
			'SELECT'	=> 't.poster, t.subject, t.posted, t.last_post',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.id='.$id
		);

		$row = null;
		if ($this->queries->changed('mr_confirm_move_topics_qr_get_redirect_topic_data', ModeratedTopicsInterface::class.'::movedTopic', $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$result = $row !== null ? new MovedTopic(Markers::markup($row['poster'] ?? ''), Markers::markup($row['subject'] ?? ''), (int) Markers::markup($row['posted'] ?? 0), (int) Markers::markup($row['last_post'] ?? 0)) : null;
		}

		$GLOBALS['moved_to'] = $row ?? ($result !== null ? array('poster' => $result->poster(), 'subject' => $result->subject(), 'posted' => $result->posted(), 'last_post' => $result->lastPost()) : false);

		return $result;
	}

	/** @return list<RedirectTopicInterface>|null */
	public function beforeAddRedirects(ModeratedTopicsInterface $subject, RedirectTopicInterface ...$redirects): ?array {
		$kept = array();
		foreach ($redirects as $redirect)
		{
			$query = array(
				'INSERT'	=> 'poster, subject, posted, last_post, moved_to, forum_id',
				'INTO'		=> 'topics',
				'VALUES'	=> '\''.self::escape($redirect->poster()).'\', \''.self::escape($redirect->subject()).'\', '.$redirect->posted().', '.$redirect->lastPost().', '.$redirect->movedTo().', '.$redirect->forumId()
			);

			if ($this->queries->changed('mr_confirm_move_topics_qr_add_redirect_topic', ModeratedTopicsInterface::class.'::addRedirects', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $redirect;
		}

		return count($kept) !== count($redirects) ? $kept : null;
	}

	public function afterMergeTarget(ModeratedTopicsInterface $subject, MergeTargetInterface $result, int $forumId, int ...$topicIds): MergeTargetInterface {
		$query = array(
			'SELECT'	=> 'COUNT(t.id), MIN(t.id)',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.id IN('.implode(',', $topicIds).') AND t.moved_to IS NULL AND t.forum_id='.$forumId
		);

		// The page script read the row by column position
		if ($this->queries->changed('mr_confirm_merge_topics_qr_verify_topic_ids', ModeratedTopicsInterface::class.'::mergeTarget', $query))
		{
			$row = PluggedQuery::listed($query);
			$lowest = Markers::markup($row[1] ?? '');
			$result = new MergeTarget((int) Markers::markup($row[0] ?? 0), $lowest !== '' ? (int) $lowest : null);
		}

		$GLOBALS['num_topics'] = $result->topicCount();
		$GLOBALS['merge_to_tid'] = $result->lowestId();

		return $result;
	}

	/** @return list<int|bool>|null */
	public function beforeRedirectMerged(ModeratedTopicsInterface $subject, int $toId, bool $leaveRedirects, int ...$topicIds): ?array {
		$query = array(
			'UPDATE'	=> 'topics',
			'SET'		=> 'moved_to='.$toId,
			'WHERE'		=> 'moved_to IN('.implode(',', $topicIds).')'
		);

		if ($leaveRedirects)
			$query['WHERE'] .= ' OR (id IN('.implode(',', $topicIds).') AND id != '.$toId.')';

		if (!$this->queries->changed('mr_confirm_merge_topics_qr_fix_redirect_topics', ModeratedTopicsInterface::class.'::redirectMerged', $query))
			return null;

		PluggedQuery::run($query);

		return array($toId, $leaveRedirects);
	}

	/** @return list<int>|null */
	public function beforeMergePosts(ModeratedTopicsInterface $subject, int $toId, int ...$topicIds): ?array {
		return $this->statement('mr_confirm_merge_topics_qr_merge_posts', 'mergePosts', array(
			'UPDATE'	=> 'posts',
			'SET'		=> 'topic_id='.$toId,
			'WHERE'		=> 'topic_id IN('.implode(',', $topicIds).')'
		), array($toId));
	}

	/** @return list<int>|null */
	public function beforeRemoveMergedSubscriptions(ModeratedTopicsInterface $subject, int $toId, int ...$topicIds): ?array {
		return $this->statement('mr_confirm_merge_topics_qr_delete_subscriptions', 'removeMergedSubscriptions', array(
			'DELETE'	=> 'subscriptions',
			'WHERE'		=> 'topic_id IN('.implode(',', $topicIds).') AND topic_id != '.$toId
		), array($toId));
	}

	/** @return list<int>|null */
	public function beforeRemoveMergedTopics(ModeratedTopicsInterface $subject, int $toId, int ...$topicIds): ?array {
		return $this->statement('mr_confirm_merge_topics_qr_delete_merged_topics', 'removeMergedTopics', array(
			'DELETE'	=> 'topics',
			'WHERE'		=> 'id IN('.implode(',', $topicIds).') AND id != '.$toId
		), array($toId));
	}

	/**
	 * @param list<int> $result
	 * @return list<int>
	 */
	public function afterRedirectForums(ModeratedTopicsInterface $subject, array $result, int ...$topicIds): array {
		$query = array(
			'SELECT'	=> 't.forum_id',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.moved_to IN('.implode(',', $topicIds).')'
		);

		if ($this->queries->changed('mr_confirm_delete_topics_qr_get_forums_to_sync', ModeratedTopicsInterface::class.'::redirectForums', $query))
			$result = self::column($query);

		$GLOBALS['forum_ids'] = array_merge(array($GLOBALS['fid'] ?? 0), $result);

		return $result;
	}

	/** @return list<int>|null */
	public function beforeRemoveTopics(ModeratedTopicsInterface $subject, int ...$topicIds): ?array {
		return $this->statement('mr_confirm_delete_topics_qr_delete_topics', 'removeTopics', array(
			'DELETE'	=> 'topics',
			'WHERE'		=> 'id IN('.implode(',', $topicIds).') OR moved_to IN('.implode(',', $topicIds).')'
		), array());
	}

	/** @return list<int>|null */
	public function beforeRemoveSubscriptions(ModeratedTopicsInterface $subject, int ...$topicIds): ?array {
		return $this->statement('mr_confirm_delete_topics_qr_delete_subscriptions', 'removeSubscriptions', array(
			'DELETE'	=> 'subscriptions',
			'WHERE'		=> 'topic_id IN('.implode(',', $topicIds).')'
		), array());
	}

	/**
	 * @param list<int> $result
	 * @return list<int>
	 */
	public function afterPostIds(ModeratedTopicsInterface $subject, array $result, int ...$topicIds): array {
		$query = array(
			'SELECT'	=> 'p.id',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.topic_id IN('.implode(',', $topicIds).')'
		);

		if ($this->queries->changed('mr_confirm_delete_topics_qr_get_deleted_posts', ModeratedTopicsInterface::class.'::postIds', $query))
			$result = self::column($query);

		$GLOBALS['post_ids'] = $result;

		return $result;
	}

	/** @return list<int>|null */
	public function beforeRemovePosts(ModeratedTopicsInterface $subject, int ...$topicIds): ?array {
		return $this->statement('mr_confirm_delete_topics_qr_delete_topic_posts', 'removePosts', array(
			'DELETE'	=> 'posts',
			'WHERE'		=> 'topic_id IN('.implode(',', $topicIds).')'
		), array());
	}

	/** @return list<int|bool>|null */
	public function beforeCloseTopics(ModeratedTopicsInterface $subject, bool $closed, int $forumId, int ...$topicIds): ?array {
		$point = $this->rows->point(self::CHECK_CLOSE);
		if ($point === '')
			return null;

		// A topic's link closed it by its id, the list by the ids selected
		$listed = $point === 'mr_open_close_multi_topics_qr_open_close_topics';

		$query = array(
			'UPDATE'	=> 'topics',
			'SET'		=> 'closed='.($closed ? 1 : 0),
			'WHERE'		=> ($listed ? 'id IN('.implode(',', $topicIds).')' : 'id='.implode(',', $topicIds)).' AND forum_id='.$forumId
		);

		return $this->statement($point, 'closeTopics', $query, array($closed, $forumId));
	}

	/** @return list<int|bool>|null */
	public function beforeStickTopics(ModeratedTopicsInterface $subject, bool $sticky, int $forumId, int ...$topicIds): ?array {
		return $this->statement($sticky ? 'mr_stick_topic_qr_stick_topic' : 'mr_unstick_topic_qr_unstick_topic', 'stickTopics', array(
			'UPDATE'	=> 'topics',
			'SET'		=> 'sticky='.($sticky ? 1 : 0),
			'WHERE'		=> 'id='.implode(',', $topicIds).' AND forum_id='.$forumId
		), array($sticky, $forumId));
	}

	/**
	 * Runs $point over a statement the page script ran once for every topic;
	 * when it changed, the changed one runs and the repository gets no topics.
	 *
	 * @template T of int|bool
	 * @param array<string, mixed> $query
	 * @param list<T> $leading the arguments before the topics
	 * @return list<T>|null
	 */
	private function statement(string $point, string $method, array $query, array $leading): ?array {
		if (!$this->queries->changed($point, ModeratedTopicsInterface::class.'::'.$method, $query))
			return null;

		PluggedQuery::run($query);

		return $leading;
	}

	/**
	 * @param array<string, mixed> $query
	 * @return list<int> the first column of every row, as fetch_row() read it
	 */
	private static function column(array $query): array {
		$db = LegacyConnection::legacy();
		$result = PluggedQuery::run($query);

		$values = array();
		while (is_array($row = $db->fetch_row($result)))
			$values[] = (int) Markers::markup(array_values($row)[0] ?? 0);

		return $values;
	}

	private static function text(mixed $value): ?string {
		return $value !== null && $value !== false ? Markers::markup($value) : null;
	}

	private static function escape(string $text): string {
		return Markers::markup(LegacyConnection::legacy()->escape($text));
	}
}
