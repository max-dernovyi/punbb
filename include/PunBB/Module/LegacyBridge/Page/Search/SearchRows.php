<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\Search\Api\Data\ResultForumInterface;
use PunBB\Module\Search\Api\Data\ResultPostInterface;
use PunBB\Module\Search\Api\Data\ResultTopicInterface;
use PunBB\Module\Search\Api\Data\SearchableForumInterface;
use PunBB\Module\Search\Model\ResultForum;
use PunBB\Module\Search\Model\ResultPost;
use PunBB\Module\Search\Model\ResultTopic;
use PunBB\Module\Search\Model\SearchableForum;

/**
 * The results as search.php handed them to extension code: the rows of its
 * queries, with any column a query point added.
 */
final class SearchRows {
	public function __construct(private readonly KeptRows $rows) {}

	/** @param array<array-key, mixed> $row */
	public function keep(object $answer, array $row): void {
		$this->rows->keep($answer, $row);
	}

	/** @return array<array-key, mixed> the result's row of the query, or one built from it */
	public function row(ResultPostInterface|ResultTopicInterface|ResultForumInterface|SearchableForumInterface $result): array {
		$row = $this->rows->row($result);
		if ($row !== null)
			return $row;

		if ($result instanceof ResultPostInterface)
			return array(
				'pid'			=> $result->id(),
				'pposter'		=> $result->poster(),
				'pposted'		=> $result->posted(),
				'poster_id'		=> $result->posterId(),
				'message'		=> $result->message(),
				'hide_smilies'	=> $result->hidesSmilies() ? 1 : 0,
				'tid'			=> $result->topicId(),
				'poster'		=> $result->topicPoster(),
				'subject'		=> $result->subject(),
				'first_post_id'	=> $result->firstPostId(),
				'posted'		=> $result->topicPosted(),
				'last_post'		=> $result->lastPost(),
				'last_post_id'	=> $result->lastPostId(),
				'last_poster'	=> $result->lastPoster(),
				'num_replies'	=> $result->replyCount(),
				'forum_id'		=> $result->forumId(),
				'forum_name'	=> $result->forumName(),
			);

		if ($result instanceof ResultTopicInterface)
			return array(
				'tid'			=> $result->id(),
				'poster'		=> $result->poster(),
				'subject'		=> $result->subject(),
				'first_post_id'	=> $result->firstPostId(),
				'posted'		=> $result->posted(),
				'last_post'		=> $result->lastPost(),
				'last_post_id'	=> $result->lastPostId(),
				'last_poster'	=> $result->lastPoster(),
				'num_replies'	=> $result->replyCount(),
				'closed'		=> $result->isClosed() ? 1 : 0,
				'sticky'		=> $result->isSticky() ? 1 : 0,
				'forum_id'		=> $result->forumId(),
				'forum_name'	=> $result->forumName(),
			);

		if ($result instanceof ResultForumInterface)
			return array(
				'cid'			=> $result->categoryId(),
				'cat_name'		=> $result->categoryName(),
				'fid'			=> $result->id(),
				'forum_name'	=> $result->name(),
				'forum_desc'	=> $result->description() !== '' ? $result->description() : null,
				'redirect_url'	=> $result->redirectUrl() !== '' ? $result->redirectUrl() : null,
				'num_topics'	=> $result->topicCount(),
				'num_posts'		=> $result->postCount(),
				'last_post'		=> $result->lastPost(),
				'last_post_id'	=> $result->lastPostId(),
				'last_poster'	=> $result->lastPoster(),
			);

		return array(
			'cid'			=> $result->categoryId(),
			'cat_name'		=> $result->categoryName(),
			'fid'			=> $result->id(),
			'forum_name'	=> $result->name(),
			'redirect_url'	=> null,
		);
	}

	/**
	 * The results of a query a point changed, each kept with its row.
	 *
	 * @param list<array<array-key, mixed>> $rows
	 * @return list<ResultPost>
	 */
	public function posts(array $rows): array {
		$posts = array();
		foreach ($rows as $row)
		{
			$post = new ResultPost(
				self::int($row, 'pid'),
				self::text($row, 'pposter'),
				self::int($row, 'poster_id'),
				self::int($row, 'pposted'),
				self::text($row, 'message'),
				self::text($row, 'hide_smilies') === '1',
				self::int($row, 'tid'),
				self::text($row, 'poster'),
				self::text($row, 'subject'),
				self::int($row, 'first_post_id'),
				self::int($row, 'posted'),
				self::int($row, 'last_post'),
				self::int($row, 'last_post_id'),
				self::text($row, 'last_poster'),
				self::int($row, 'num_replies'),
				self::int($row, 'forum_id'),
				self::text($row, 'forum_name')
			);
			$this->keep($post, $row);
			$posts[] = $post;
		}

		return $posts;
	}

	/**
	 * @param list<array<array-key, mixed>> $rows
	 * @return list<ResultTopic>
	 */
	public function topics(array $rows, ?int $posterId): array {
		$topics = array();
		foreach ($rows as $row)
		{
			$topic = new ResultTopic(
				self::int($row, 'tid'),
				self::text($row, 'poster'),
				self::text($row, 'subject'),
				self::int($row, 'first_post_id'),
				self::int($row, 'posted'),
				self::int($row, 'last_post'),
				self::int($row, 'last_post_id'),
				self::text($row, 'last_poster'),
				self::int($row, 'num_replies'),
				self::text($row, 'closed') === '1',
				self::text($row, 'sticky') === '1',
				self::int($row, 'forum_id'),
				self::text($row, 'forum_name'),
				$posterId !== null && isset($row['has_posted']) && self::int($row, 'has_posted') === $posterId
			);
			$this->keep($topic, $row);
			$topics[] = $topic;
		}

		return $topics;
	}

	/**
	 * @param list<array<array-key, mixed>> $rows
	 * @return list<ResultForum>
	 */
	public function forums(array $rows): array {
		$forums = array();
		foreach ($rows as $row)
		{
			$forum = new ResultForum(
				self::int($row, 'cid'),
				self::text($row, 'cat_name'),
				self::int($row, 'fid'),
				self::text($row, 'forum_name'),
				self::text($row, 'forum_desc'),
				self::text($row, 'redirect_url'),
				self::int($row, 'num_topics'),
				self::int($row, 'num_posts'),
				isset($row['last_post']) && self::text($row, 'last_post') !== '' ? self::int($row, 'last_post') : null,
				isset($row['last_post_id']) ? self::int($row, 'last_post_id') : null,
				isset($row['last_poster']) ? self::text($row, 'last_poster') : null
			);
			$this->keep($forum, $row);
			$forums[] = $forum;
		}

		return $forums;
	}

	/**
	 * @param list<array<array-key, mixed>> $rows
	 * @return list<SearchableForum>
	 */
	public function searchable(array $rows): array {
		$forums = array();
		foreach ($rows as $row)
		{
			$forum = new SearchableForum(self::int($row, 'cid'), self::text($row, 'cat_name'), self::int($row, 'fid'), self::text($row, 'forum_name'));
			$this->keep($forum, $row);
			$forums[] = $forum;
		}

		return $forums;
	}

	/** @param array<array-key, mixed> $row */
	private static function int(array $row, string $column): int {
		return (int) Markers::markup($row[$column] ?? 0);
	}

	/** @param array<array-key, mixed> $row */
	private static function text(array $row, string $column): string {
		return Markers::markup($row[$column] ?? '');
	}
}
