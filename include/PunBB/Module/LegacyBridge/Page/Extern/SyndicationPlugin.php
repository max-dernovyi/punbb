<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extern;

use PunBB\Module\Extern\Api\Data\FeedEntryInterface;
use PunBB\Module\Extern\Api\Data\FeedTopicInterface;
use PunBB\Module\Extern\Api\Data\OnlineVisitorInterface;
use PunBB\Module\Extern\Api\Data\StatisticsInterface;
use PunBB\Module\Extern\Api\SyndicationInterface;
use PunBB\Module\Extern\Model\FeedEntry;
use PunBB\Module\Extern\Model\FeedTopic;
use PunBB\Module\Extern\Model\OnlineVisitor;
use PunBB\Module\Extern\Model\Statistics;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;

/**
 * Syndication's query points, with the query arrays extern.php built; the
 * topic a feed of posts follows is left in $tid and $cur_topic, as the page
 * script kept it.
 */
final class SyndicationPlugin {
	public function __construct(private readonly PluggedQuery $queries, private readonly KeptRows $rows) {}

	public function afterTopic(SyndicationInterface $subject, ?FeedTopicInterface $result, int $topicId, int $groupId): ?FeedTopicInterface {
		$GLOBALS['tid'] = $topicId;

		$query = array(
			'SELECT'	=> 't.subject, t.first_post_id',
			'FROM'		=> 'topics AS t',
			'JOINS'		=> array(
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=t.forum_id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.moved_to IS NULL and t.id='.$topicId
		);

		if ($this->queries->changed('ex_qr_get_topic_data', SyndicationInterface::class.'::topic', $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$result = $row !== null ? new FeedTopic($topicId, Markers::markup($row['subject'] ?? ''), (int) Markers::markup($row['first_post_id'] ?? 0)) : null;
		}

		$GLOBALS['cur_topic'] = $result !== null ? array('subject' => $result->subject(), 'first_post_id' => $result->firstPostId()) : false;

		return $result;
	}

	/**
	 * @param list<FeedEntryInterface> $result
	 * @return list<FeedEntryInterface>
	 */
	public function afterPosts(SyndicationInterface $subject, array $result, int $topicId, int $limit): array {
		$query = array(
			'SELECT'	=> 'p.id, p.poster, p.message, p.hide_smilies, p.posted, p.poster_id, u.email_setting, u.email, p.poster_email',
			'FROM'		=> 'posts AS p',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'users AS u',
					'ON'			=> 'u.id = p.poster_id'
				)
			),
			'WHERE'		=> 'p.topic_id='.$topicId,
			'ORDER BY'	=> 'p.posted DESC',
			'LIMIT'		=> $limit
		);

		if (!$this->queries->changed('ex_qr_get_posts', SyndicationInterface::class.'::posts', $query))
			return $result;

		return $this->entries($query, false);
	}

	/**
	 * @param list<int> $forumIds
	 * @param list<FeedEntryInterface> $result
	 * @return list<FeedEntryInterface>
	 */
	public function afterTopics(SyndicationInterface $subject, array $result, int $groupId, array $forumIds, bool $excluding, bool $byLastPost, int $limit): array {
		$query = array(
			'SELECT'	=> 't.id, t.poster, t.posted, t.subject, p.message, p.hide_smilies, u.email_setting, u.email, p.poster_id, p.poster_email',
			'FROM'		=> 'topics AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'posts AS p',
					'ON'			=> 'p.id = t.first_post_id'
				),
				array(
					'INNER JOIN'	=> 'users AS u',
					'ON'			=> 'u.id = p.poster_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id = t.forum_id AND fp.group_id = '.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum = 1) AND t.moved_to IS NULL'.($forumIds !== array() ? ' AND t.forum_id '.($excluding ? 'NOT IN' : 'IN').'('.implode(',', $forumIds).')' : ''),
			'ORDER BY'	=> ($byLastPost ? 't.last_post' : 't.posted').' DESC',
			'LIMIT'		=> $limit
		);

		if (!$this->queries->changed('ex_qr_get_topics', SyndicationInterface::class.'::topics', $query))
			return $result;

		return $this->entries($query, true);
	}

	/**
	 * @param list<OnlineVisitorInterface> $result
	 * @return list<OnlineVisitorInterface>
	 */
	public function afterOnlineVisitors(SyndicationInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'o.user_id, o.ident',
			'FROM'		=> 'online AS o',
			'WHERE'		=> 'o.idle=0',
			'ORDER BY'	=> 'o.ident'
		);

		if (!$this->queries->changed('ex_qr_get_users_online', SyndicationInterface::class.'::onlineVisitors', $query))
			return $result;

		$visitors = array();
		foreach (PluggedQuery::rows($query) as $row)
			$visitors[] = new OnlineVisitor((int) Markers::markup($row['user_id'] ?? 1), Markers::markup($row['ident'] ?? ''));

		return $visitors;
	}

	public function afterStatistics(SyndicationInterface $subject, StatisticsInterface $result): StatisticsInterface {
		$users = array(
			'SELECT'	=> 'COUNT(u.id) - 1',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.group_id != '.Markers::markup(\FORUM_UNVERIFIED)
		);

		$newest = array(
			'SELECT'	=> 'u.id, u.username',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.group_id != '.Markers::markup(\FORUM_UNVERIFIED),
			'ORDER BY'	=> 'u.registered DESC',
			'LIMIT'		=> '1'
		);

		$posts = array(
			'SELECT'	=> 'SUM(f.num_topics), SUM(f.num_posts)',
			'FROM'		=> 'forums AS f'
		);

		$userCount = $result->userCount();
		if ($this->queries->changed('ex_qr_get_user_count', SyndicationInterface::class.'::statistics', $users))
			$userCount = (int) Markers::markup(PluggedQuery::value($users));

		$newestId = $result->newestUserId();
		$newestName = $result->newestUsername();
		if ($this->queries->changed('ex_qr_get_newest_user', SyndicationInterface::class.'::statistics', $newest))
		{
			$row = PluggedQuery::rows($newest)[0] ?? array();
			$newestId = (int) Markers::markup($row['id'] ?? 0);
			$newestName = Markers::markup($row['username'] ?? '');
		}

		$topicCount = $result->topicCount();
		$postCount = $result->postCount();
		if ($this->queries->changed('ex_qr_get_post_stats', SyndicationInterface::class.'::statistics', $posts))
		{
			$values = array_values(PluggedQuery::rows($posts)[0] ?? array());
			$topicCount = (int) Markers::markup($values[0] ?? 0);
			$postCount = (int) Markers::markup($values[1] ?? 0);
		}

		return new Statistics($userCount, $newestId, $newestName, $topicCount, $postCount);
	}

	/**
	 * @param array<string, mixed> $query
	 * @return list<FeedEntryInterface>
	 */
	private function entries(array $query, bool $topics): array {
		$entries = array();
		foreach (PluggedQuery::rows($query) as $row)
		{
			$entry = new FeedEntry(
				(int) Markers::markup($row['id'] ?? 0),
				$topics ? Markers::markup($row['subject'] ?? '') : '',
				Markers::markup($row['poster'] ?? ''),
				(int) Markers::markup($row['poster_id'] ?? 0),
				(int) Markers::markup($row['posted'] ?? 0),
				Markers::markup($row['message'] ?? ''),
				Markers::markup($row['hide_smilies'] ?? 0) === '1',
				Markers::markup($row['email'] ?? ''),
				Markers::markup($row['email_setting'] ?? '') === '0',
				Markers::markup($row['poster_email'] ?? '')
			);

			$this->rows->keep($entry, $row);
			$entries[] = $entry;
		}

		return $entries;
	}
}
