<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Post;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Post\Api\Data\LocationInterface;
use PunBB\Module\Post\Api\Data\QuoteInterface;
use PunBB\Module\Post\Api\Data\ReviewPostInterface;
use PunBB\Module\Post\Api\PostingInterface;
use PunBB\Module\Post\Model\Quote;

/**
 * The posting page's query points, with the query arrays post.php built. What
 * each answered is left in the variables the page script kept it in: $tid,
 * $fid and $cur_posting, $qid and $quote_info, $forum_page['total_post_count']
 * and $posts.
 */
final class PostingPlugin {
	public function __construct(private readonly PluggedQuery $queries, private readonly PostingRows $rows) {}

	public function afterTopic(PostingInterface $subject, ?LocationInterface $result, int $topicId, int $groupId, int $userId): ?LocationInterface {
		$GLOBALS['tid'] = $topicId;
		$GLOBALS['fid'] = 0;

		$query = array(
			'SELECT'	=> 'f.id, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics, t.subject, t.closed, s.user_id AS is_subscribed',
			'FROM'		=> 'topics AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'f.id=t.forum_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$groupId.')'
				),
				array(
					'LEFT JOIN'		=> 'subscriptions AS s',
					'ON'			=> '(t.id=s.topic_id AND s.user_id='.$userId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$topicId
		);

		return $this->located('po_qr_get_topic_forum_info', 'topic', $query, $result, $topicId, $userId);
	}

	public function afterForum(PostingInterface $subject, ?LocationInterface $result, int $forumId, int $groupId): ?LocationInterface {
		$GLOBALS['tid'] = 0;
		$GLOBALS['fid'] = $forumId;

		$query = array(
			'SELECT'	=> 'f.id, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics',
			'FROM'		=> 'forums AS f',
			'JOINS'		=> array(
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$forumId
		);

		return $this->located('po_qr_get_forum_info', 'forum', $query, $result, 0, null);
	}

	public function afterQuote(PostingInterface $subject, ?QuoteInterface $result, int $postId, int $topicId): ?QuoteInterface {
		$GLOBALS['qid'] = $postId;

		$query = array(
			'SELECT'	=> 'p.poster, p.message',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'id='.$postId.' AND topic_id='.$topicId
		);

		if ($this->queries->changed('po_qr_get_quote', PostingInterface::class.'::quote', $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$GLOBALS['quote_info'] = $row ?? false;

			return $row !== null ? new Quote(Markers::markup($row['poster'] ?? ''), Markers::markup($row['message'] ?? '')) : null;
		}

		$GLOBALS['quote_info'] = $result !== null ? array('poster' => $result->poster(), 'message' => $result->message()) : false;

		return $result;
	}

	public function afterReviewCount(PostingInterface $subject, int $result, int $topicId): int {
		$query = array(
			'SELECT'	=> 'count(p.id)',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'topic_id='.$topicId
		);

		if ($this->queries->changed('po_topic_review_qr_get_post_count', PostingInterface::class.'::reviewCount', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query));

		ForumPage::set('total_post_count', $result);

		return $result;
	}

	/**
	 * @param list<ReviewPostInterface> $result
	 * @return list<ReviewPostInterface>
	 */
	public function afterReview(PostingInterface $subject, array $result, int $topicId, int $limit): array {
		$query = array(
			'SELECT'	=> 'p.id, p.poster, p.message, p.hide_smilies, p.posted',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'topic_id='.$topicId,
			'ORDER BY'	=> 'id DESC',
			'LIMIT'		=> $limit
		);

		if ($this->queries->changed('po_topic_review_qr_get_topic_review_posts', PostingInterface::class.'::review', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$post = PostingRows::postOf($row);
				$this->rows->keep($post, $row);
				$result[] = $post;
			}
		}

		$GLOBALS['posts'] = array_map($this->rows->post(...), $result);

		return $result;
	}

	/** @param array<string, mixed> $query */
	private function located(string $point, string $method, array $query, ?LocationInterface $result, int $topicId, ?int $subscriberId): ?LocationInterface {
		if ($this->queries->changed($point, PostingInterface::class.'::'.$method, $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$result = null;

			if ($row !== null)
			{
				$result = PostingRows::locationOf($topicId, $row);
				$this->rows->keep($result, $row);
			}
		}

		$GLOBALS['cur_posting'] = $result !== null ? $this->rows->location($result, $subscriberId) : false;

		return $result;
	}
}
