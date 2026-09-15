<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Index;

use PunBB\Module\Index\Api\BoardIndexInterface;
use PunBB\Module\Index\Api\Data\ForumInterface;
use PunBB\Module\Index\Api\Data\OnlineVisitorInterface;
use PunBB\Module\Index\Api\Data\StatisticsInterface;
use PunBB\Module\Index\Api\Data\TopicActivityInterface;
use PunBB\Module\Index\Model\Forum;
use PunBB\Module\Index\Model\Moderator;
use PunBB\Module\Index\Model\OnlineVisitor;
use PunBB\Module\Index\Model\Statistics;
use PunBB\Module\Index\Model\TopicActivity;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;

/**
 * The board index's query points, with the query arrays index.php built, and
 * the points of the statistics cache it read, with those generate_stats_cache()
 * built. What the page kept of each answer is left in its global.
 */
final class BoardIndexPlugin {
	public function __construct(private readonly PluggedQuery $queries, private readonly PageScope $scope, private readonly KeptRows $rows) {}

	/**
	 * @param list<TopicActivityInterface> $result
	 * @return list<TopicActivityInterface>
	 */
	public function afterActiveTopics(BoardIndexInterface $subject, array $result, int $groupId, int $since): array {
		$query = array(
			'SELECT'	=> 't.forum_id, t.id, t.last_post',
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
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.last_post>'.$since.' AND t.moved_to IS NULL'
		);

		if ($this->queries->changed('in_qr_get_new_topics', BoardIndexInterface::class.'::activeTopics', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
				$result[] = new TopicActivity((int) Markers::markup($row['forum_id'] ?? 0), (int) Markers::markup($row['id'] ?? 0), (int) Markers::markup($row['last_post'] ?? 0));
		}

		$new_topics = array();
		foreach ($result as $topic)
			$new_topics[$topic->forumId()][$topic->topicId()] = $topic->lastPost();

		$GLOBALS['new_topics'] = $new_topics;

		return $result;
	}

	/**
	 * @param list<ForumInterface> $result
	 * @return list<ForumInterface>
	 */
	public function afterForums(BoardIndexInterface $subject, array $result, int $groupId): array {
		$query = array(
			'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.forum_desc, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster',
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
			'WHERE'		=> 'fp.read_forum IS NULL OR fp.read_forum=1',
			'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
		);

		if (!$this->queries->changed('in_qr_get_cats_and_forums', BoardIndexInterface::class.'::forums', $query))
			return $result;

		$forums = array();
		foreach (PluggedQuery::rows($query) as $row)
		{
			$forum = new Forum(
				(int) Markers::markup($row['cid'] ?? 0),
				Markers::markup($row['cat_name'] ?? ''),
				(int) Markers::markup($row['fid'] ?? 0),
				Markers::markup($row['forum_name'] ?? ''),
				Markers::markup($row['forum_desc'] ?? ''),
				Markers::markup($row['redirect_url'] ?? ''),
				Moderator::listOf(isset($row['moderators']) ? Markers::markup($row['moderators']) : null),
				(int) Markers::markup($row['num_topics'] ?? 0),
				(int) Markers::markup($row['num_posts'] ?? 0),
				isset($row['last_post']) && Markers::markup($row['last_post']) !== '' ? (int) Markers::markup($row['last_post']) : null,
				isset($row['last_post_id']) ? (int) Markers::markup($row['last_post_id']) : null,
				isset($row['last_poster']) ? Markers::markup($row['last_poster']) : null
			);

			$this->rows->keep($forum, $row);
			$forums[] = $forum;
		}

		return $forums;
	}

	public function afterStatistics(BoardIndexInterface $subject, StatisticsInterface $result): StatisticsInterface {
		$this->scope->plugged('ch_fn_generate_stats_cache_start', BoardIndexInterface::class.'::statistics');

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
			'SELECT'	=> 'SUM(f.num_topics) AS num_topics, SUM(f.num_posts) AS num_posts',
			'FROM'		=> 'forums AS f'
		);

		$userCount = $result->userCount();
		if ($this->queries->changed('ch_fn_generate_stats_cache_qr_get_user_count', BoardIndexInterface::class.'::statistics', $users))
			$userCount = (int) Markers::markup(PluggedQuery::value($users));

		$last_user = array('id' => $result->newestUserId(), 'username' => $result->newestUsername());
		if ($this->queries->changed('ch_fn_generate_stats_cache_qr_get_newest_user', BoardIndexInterface::class.'::statistics', $newest))
			$last_user = PluggedQuery::rows($newest)[0] ?? array();

		$topics_and_posts = array('num_topics' => $result->topicCount(), 'num_posts' => $result->postCount());
		if ($this->queries->changed('ch_fn_generate_stats_cache_qr_get_post_stats', BoardIndexInterface::class.'::statistics', $posts))
			$topics_and_posts = PluggedQuery::rows($posts)[0] ?? array();

		$GLOBALS['forum_stats'] = array(
			'total_users'	=> $userCount,
			'last_user'		=> $last_user,
			'total_topics'	=> $topics_and_posts['num_topics'] ?? 0,
			'total_posts'	=> $topics_and_posts['num_posts'] ?? 0,
			'cached'		=> time(),
		);

		return new Statistics($userCount, (int) Markers::markup($last_user['id'] ?? 0), Markers::markup($last_user['username'] ?? ''),
			(int) Markers::markup($topics_and_posts['num_topics'] ?? 0), (int) Markers::markup($topics_and_posts['num_posts'] ?? 0));
	}

	/**
	 * @param list<OnlineVisitorInterface> $result
	 * @return list<OnlineVisitorInterface>
	 */
	public function afterOnlineVisitors(BoardIndexInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'o.user_id, o.ident',
			'FROM'		=> 'online AS o',
			'WHERE'		=> 'o.idle=0',
			'ORDER BY'	=> 'o.ident'
		);

		if (!$this->queries->changed('in_users_online_qr_get_online_info', BoardIndexInterface::class.'::onlineVisitors', $query))
			return $result;

		$visitors = array();
		foreach (PluggedQuery::rows($query) as $row)
		{
			$visitor = new OnlineVisitor((int) Markers::markup($row['user_id'] ?? 1), Markers::markup($row['ident'] ?? ''));
			$this->rows->keep($visitor, $row);
			$visitors[] = $visitor;
		}

		return $visitors;
	}
}
