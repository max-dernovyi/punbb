<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Post;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\Post\Api\Data\LocationInterface;
use PunBB\Module\Post\Api\Data\ReviewPostInterface;
use PunBB\Module\Post\Model\Location;
use PunBB\Module\Post\Model\Moderator;
use PunBB\Module\Post\Model\ReviewPost;

/**
 * Where a post goes and the posts below the form, as post.php handed them to
 * extension code: the rows of its queries, with any column a query point added.
 */
final class PostingRows {
	public function __construct(private readonly KeptRows $rows) {}

	/** @param array<array-key, mixed> $row */
	public function keep(object $answer, array $row): void {
		$this->rows->keep($answer, $row);
	}

	/** @return array<array-key, mixed> the location's row of the query, or one built from it */
	public function location(LocationInterface $location, ?int $subscriberId = null): array {
		$row = $this->rows->row($location);
		if ($row !== null)
			return $row;

		$row = array(
			'id'			=> $location->forumId(),
			'forum_name'	=> $location->forumName(),
			'moderators'	=> Moderator::stored($location->moderators()),
			'redirect_url'	=> $location->redirectUrl() !== '' ? $location->redirectUrl() : null,
			'post_replies'	=> self::permission($location->groupPostsReplies()),
			'post_topics'	=> self::permission($location->groupPostsTopics()),
		);

		if ($location->topicId() > 0)
			$row += array(
				'subject'		=> $location->subject(),
				'closed'		=> $location->topicClosed() ? 1 : 0,
				'is_subscribed'	=> $location->subscribed() ? $subscriberId : null,
			);

		return $row;
	}

	/** @return array<array-key, mixed> the post's row of the query, or one built from it */
	public function post(ReviewPostInterface $post): array {
		return $this->rows->row($post) ?? array(
			'id'			=> $post->id(),
			'poster'		=> $post->poster(),
			'message'		=> $post->message(),
			'hide_smilies'	=> $post->hidesSmilies() ? 1 : 0,
			'posted'		=> $post->postedAt(),
		);
	}

	/**
	 * A location from a row of the query a point changed.
	 *
	 * @param array<array-key, mixed> $row
	 */
	public static function locationOf(int $topicId, array $row): Location {
		return new Location(
			(int) Markers::markup($row['id'] ?? 0),
			Markers::markup($row['forum_name'] ?? ''),
			Moderator::listOf(isset($row['moderators']) ? Markers::markup($row['moderators']) : null),
			Markers::markup($row['redirect_url'] ?? ''),
			self::permissionOf($row['post_replies'] ?? null),
			self::permissionOf($row['post_topics'] ?? null),
			$topicId,
			$topicId > 0 ? Markers::markup($row['subject'] ?? '') : '',
			Markers::markup($row['closed'] ?? 0) === '1',
			isset($row['is_subscribed']) && Markers::markup($row['is_subscribed']) !== ''
		);
	}

	/**
	 * A post from a row of the query a point changed.
	 *
	 * @param array<array-key, mixed> $row
	 */
	public static function postOf(array $row): ReviewPost {
		return new ReviewPost(
			(int) Markers::markup($row['id'] ?? 0),
			Markers::markup($row['poster'] ?? ''),
			Markers::markup($row['message'] ?? ''),
			Markers::markup($row['hide_smilies'] ?? 0) === '1',
			(int) Markers::markup($row['posted'] ?? 0)
		);
	}

	private static function permission(?bool $allowed): ?int {
		return $allowed !== null ? ($allowed ? 1 : 0) : null;
	}

	private static function permissionOf(mixed $stored): ?bool {
		$stored = $stored !== null ? Markers::markup($stored) : '';

		return $stored !== '' ? $stored === '1' : null;
	}
}
