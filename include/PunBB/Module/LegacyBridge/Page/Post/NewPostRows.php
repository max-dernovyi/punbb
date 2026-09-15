<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Post;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Post\Api\Data\NewPostInterface;
use PunBB\Module\Post\Model\NewPost;

/**
 * A new post as $post_info, the array post.php handed add_post() and
 * add_topic(), and back. A key extension code added to the array stays in it.
 */
final class NewPostRows {
	/**
	 * @param mixed $kept the array a point may have added keys to
	 * @return array<array-key, mixed>
	 */
	public static function row(NewPostInterface $post, mixed $kept = null): array {
		$row = array(
			'is_guest'		=> $post->isGuest(),
			'poster'		=> $post->poster(),
			'poster_id'		=> $post->posterId(),
			'poster_email'	=> $post->posterEmail(),
			'subject'		=> $post->subject(),
			'message'		=> $post->message(),
			'hide_smilies'	=> $post->hidesSmilies() ? 1 : 0,
			'posted'		=> $post->postedAt(),
		);

		if ($post->topicId() > 0)
			$row += array(
				'subscr_action'	=> $post->subscription(),
				'topic_id'		=> $post->topicId(),
				'forum_id'		=> $post->forumId(),
			);
		else
			$row += array(
				'subscribe'		=> $post->subscription() === NewPostInterface::SUBSCRIPTION_STARTED,
				'forum_id'		=> $post->forumId(),
				'forum_name'	=> $post->forumName(),
			);

		$row += array(
			'update_user'	=> true,
			'update_unread'	=> true,
		);

		return is_array($kept) ? array_replace($kept, $row) : $row;
	}

	/** The post $row describes, what it leaves out taken from $post. */
	public static function post(mixed $row, NewPostInterface $post): NewPostInterface {
		if (!is_array($row))
			return $post;

		$reply = $post->topicId() > 0;
		$email = $row['poster_email'] ?? null;

		return new NewPost(
			!empty($row['is_guest']),
			Markers::markup($row['poster'] ?? $post->poster()),
			(int) Markers::markup($row['poster_id'] ?? $post->posterId()),
			$email !== null && Markers::markup($email) !== '' ? Markers::markup($email) : null,
			Markers::markup($row['subject'] ?? $post->subject()),
			Markers::markup($row['message'] ?? $post->message()),
			!empty($row['hide_smilies']),
			(int) Markers::markup($row['posted'] ?? $post->postedAt()),
			$reply ? (int) Markers::markup($row['topic_id'] ?? $post->topicId()) : 0,
			(int) Markers::markup($row['forum_id'] ?? $post->forumId()),
			Markers::markup($row['forum_name'] ?? $post->forumName()),
			$reply ? (int) Markers::markup($row['subscr_action'] ?? $post->subscription())
				: (!empty($row['subscribe']) ? NewPostInterface::SUBSCRIPTION_STARTED : NewPostInterface::SUBSCRIPTION_KEPT)
		);
	}
}
