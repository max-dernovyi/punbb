<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\Moderate\Api\Data\ListedTopicInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedForumInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedPostInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedTopicInterface;
use PunBB\Module\Moderate\Api\Data\TargetForumInterface;
use PunBB\Module\Moderate\Model\ListedTopic;
use PunBB\Module\Moderate\Model\ModeratedForum;
use PunBB\Module\Moderate\Model\ModeratedPost;

/**
 * The forums, topics and posts as moderate.php handed them to extension code:
 * the rows of their queries, with any column a query point added. It also
 * keeps which change the page was asked for, as the steps selecting it saw it:
 * a check the page script ran at a point of each change's own.
 */
final class ModerationRows {
	/** @var array<string, string> the check => its point for the change selected */
	private array $points = array();

	public function __construct(private readonly KeptRows $rows) {}

	/** @param array<array-key, mixed> $row */
	public function keep(object $answer, array $row): void {
		$this->rows->keep($answer, $row);
	}

	/** The change selected runs $check at $point. */
	public function select(string $check, string $point): void {
		$this->points[$check] = $point;
	}

	/** The point $check runs at for the change selected; '' before one is. */
	public function point(string $check): string {
		return $this->points[$check] ?? '';
	}

	/** @return array<array-key, mixed> */
	public function forum(ModeratedForumInterface $forum): array {
		$moderators = array();
		foreach ($forum->moderators() as $moderator)
			$moderators[$moderator->username()] = $moderator->userId();

		return $this->rows->row($forum) ?? array(
			'forum_name'	=> $forum->name(),
			'redirect_url'	=> $forum->redirectUrl() !== '' ? $forum->redirectUrl() : null,
			'num_topics'	=> $forum->topicCount(),
			'moderators'	=> $moderators !== array() ? serialize($moderators) : null,
			'sort_by'		=> $forum->sortsByPosted() ? 1 : 0,
		);
	}

	/** @return array<array-key, mixed> */
	public function topic(ModeratedTopicInterface $topic): array {
		return $this->rows->row($topic) ?? array(
			'subject'		=> $topic->subject(),
			'poster'		=> $topic->poster(),
			'first_post_id'	=> $topic->firstPostId(),
			'posted'		=> $topic->posted(),
			'num_replies'	=> $topic->replyCount(),
		);
	}

	/** @return array<array-key, mixed> */
	public function post(ModeratedPostInterface $post): array {
		return $this->rows->row($post) ?? array(
			'title'			=> $post->posterTitle() !== '' ? $post->posterTitle() : null,
			'num_posts'		=> $post->posterPostCount(),
			'g_id'			=> $post->posterGroupId(),
			'g_user_title'	=> $post->posterGroupTitle(),
			'id'			=> $post->id(),
			'poster'		=> $post->poster(),
			'poster_id'		=> $post->posterId(),
			'message'		=> $post->message(),
			'hide_smilies'	=> $post->hidesSmilies() ? 1 : 0,
			'posted'		=> $post->posted(),
			'edited'		=> $post->edited(),
			'edited_by'		=> $post->editedBy() !== '' ? $post->editedBy() : null,
		);
	}

	/** @return array<array-key, mixed> */
	public function target(TargetForumInterface $forum): array {
		return $this->rows->row($forum) ?? array('cid' => $forum->categoryId(), 'cat_name' => $forum->categoryName(), 'fid' => $forum->forumId(), 'forum_name' => $forum->forumName());
	}

	/** @return array<array-key, mixed> */
	public function listed(ListedTopicInterface $topic, ?int $postedBy): array {
		$row = $this->rows->row($topic);
		if ($row !== null)
			return $row;

		$row = array(
			'id'			=> $topic->id(),
			'poster'		=> $topic->poster(),
			'subject'		=> $topic->subject(),
			'posted'		=> $topic->posted(),
			'last_post'		=> $topic->lastPost(),
			'last_post_id'	=> $topic->lastPostId(),
			'last_poster'	=> $topic->lastPoster(),
			'num_views'		=> $topic->viewCount(),
			'num_replies'	=> $topic->replyCount(),
			'closed'		=> $topic->isClosed() ? 1 : 0,
			'sticky'		=> $topic->isSticky() ? 1 : 0,
			'moved_to'		=> $topic->movedTo(),
		);

		if ($postedBy !== null)
			$row['has_posted'] = $topic->hasPosted() ? $postedBy : null;

		return $row;
	}

	/** @param array<array-key, mixed> $row a row of the forum's query */
	public static function forumOf(int $id, array $row): ModeratedForum {
		return new ModeratedForum($id, Markers::markup($row['forum_name'] ?? ''), Markers::markup($row['redirect_url'] ?? ''), (int) Markers::markup($row['num_topics'] ?? 0),
			ModeratedForum::moderatorsOf(isset($row['moderators']) ? Markers::markup($row['moderators']) : null), Markers::markup($row['sort_by'] ?? '') === '1');
	}

	/** @param array<array-key, mixed> $row a row of the posts' query */
	public static function postOf(array $row): ModeratedPost {
		return new ModeratedPost(
			(int) Markers::markup($row['id'] ?? 0),
			Markers::markup($row['poster'] ?? ''),
			(int) Markers::markup($row['poster_id'] ?? 1),
			Markers::markup($row['message'] ?? ''),
			Markers::markup($row['hide_smilies'] ?? 0) === '1',
			(int) Markers::markup($row['posted'] ?? 0),
			isset($row['edited']) && Markers::markup($row['edited']) !== '' ? (int) Markers::markup($row['edited']) : null,
			Markers::markup($row['edited_by'] ?? ''),
			Markers::markup($row['title'] ?? ''),
			(int) Markers::markup($row['num_posts'] ?? 0),
			(int) Markers::markup($row['g_id'] ?? 0),
			isset($row['g_user_title']) ? Markers::markup($row['g_user_title']) : null
		);
	}

	/** @param array<array-key, mixed> $row a row of the topics' query */
	public static function listedOf(array $row, ?int $postedBy): ListedTopic {
		return new ListedTopic(
			(int) Markers::markup($row['id'] ?? 0),
			Markers::markup($row['poster'] ?? ''),
			Markers::markup($row['subject'] ?? ''),
			(int) Markers::markup($row['posted'] ?? 0),
			(int) Markers::markup($row['last_post'] ?? 0),
			(int) Markers::markup($row['last_post_id'] ?? 0),
			Markers::markup($row['last_poster'] ?? ''),
			(int) Markers::markup($row['num_views'] ?? 0),
			(int) Markers::markup($row['num_replies'] ?? 0),
			Markers::markup($row['closed'] ?? 0) === '1',
			Markers::markup($row['sticky'] ?? 0) === '1',
			isset($row['moved_to']) && Markers::markup($row['moved_to']) !== '' ? (int) Markers::markup($row['moved_to']) : null,
			$postedBy !== null && isset($row['has_posted']) && (int) Markers::markup($row['has_posted']) === $postedBy
		);
	}
}
