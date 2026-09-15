<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewtopic;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\Viewtopic\Api\Data\TopicPostInterface;
use PunBB\Module\Viewtopic\Api\Data\ViewedTopicInterface;
use PunBB\Module\Viewtopic\Model\Moderator;
use PunBB\Module\Viewtopic\Model\TopicPost;
use PunBB\Module\Viewtopic\Model\ViewedTopic;

/**
 * A topic and its posts as viewtopic.php handed them to extension code: the
 * rows of its queries, with any column a query point added.
 */
final class ViewedTopicRows {
	public function __construct(private readonly KeptRows $rows) {}

	/** @param array<array-key, mixed> $row */
	public function keep(object $answer, array $row): void {
		$this->rows->keep($answer, $row);
	}

	/** @return array<array-key, mixed> the topic's row of the query, or one built from it */
	public function topic(ViewedTopicInterface $topic, ?int $subscriberId = null): array {
		$row = $this->rows->row($topic);
		if ($row !== null)
			return $row;

		$row = array(
			'subject'		=> $topic->subject(),
			'first_post_id'	=> $topic->firstPostId(),
			'closed'		=> $topic->isClosed() ? 1 : 0,
			'num_replies'	=> $topic->replyCount(),
			'sticky'		=> $topic->isSticky() ? 1 : 0,
			'forum_id'		=> $topic->forumId(),
			'forum_name'	=> $topic->forumName(),
			'moderators'	=> Moderator::stored($topic->moderators()),
			'post_replies'	=> $topic->groupPostsReplies() !== null ? ($topic->groupPostsReplies() ? 1 : 0) : null,
		);

		if ($subscriberId !== null)
			$row['is_subscribed'] = $topic->isSubscribed() ? $subscriberId : null;

		return $row;
	}

	/** @return array<array-key, mixed> the post's row of the query, or one built from it */
	public function post(TopicPostInterface $post): array {
		return $this->rows->row($post) ?? array(
			'email'			=> $post->email(),
			'title'			=> $post->title(),
			'url'			=> $post->url(),
			'location'		=> $post->location(),
			'signature'		=> $post->signature(),
			'email_setting'	=> $post->emailSetting(),
			'num_posts'		=> $post->postCount(),
			'registered'	=> $post->registered(),
			'admin_note'	=> $post->adminNote(),
			'avatar'		=> $post->avatar(),
			'avatar_width'	=> $post->avatarWidth(),
			'avatar_height'	=> $post->avatarHeight(),
			'id'			=> $post->id(),
			'username'		=> $post->poster(),
			'poster_id'		=> $post->posterId(),
			'poster_ip'		=> $post->posterIp(),
			'poster_email'	=> $post->posterEmail(),
			'message'		=> $post->message(),
			'hide_smilies'	=> $post->hidesSmilies() ? 1 : 0,
			'posted'		=> $post->posted(),
			'edited'		=> $post->edited(),
			'edited_by'		=> $post->editedBy(),
			'g_id'			=> $post->groupId(),
			'g_user_title'	=> $post->groupTitle(),
			'is_online'		=> $post->isOnline() ? $post->posterId() : null,
		);
	}

	/**
	 * A topic from a row of the query a point changed.
	 *
	 * @param array<array-key, mixed> $row
	 */
	public static function topicOf(int $topicId, array $row): ViewedTopic {
		$postReplies = isset($row['post_replies']) ? Markers::markup($row['post_replies']) : '';

		return new ViewedTopic(
			$topicId,
			Markers::markup($row['subject'] ?? ''),
			(int) Markers::markup($row['first_post_id'] ?? 0),
			Markers::markup($row['closed'] ?? 0) === '1',
			Markers::markup($row['sticky'] ?? 0) === '1',
			(int) Markers::markup($row['num_replies'] ?? 0),
			(int) Markers::markup($row['forum_id'] ?? 0),
			Markers::markup($row['forum_name'] ?? ''),
			Moderator::listOf(isset($row['moderators']) ? Markers::markup($row['moderators']) : null),
			$postReplies !== '' ? $postReplies === '1' : null,
			isset($row['is_subscribed']) && Markers::markup($row['is_subscribed']) !== ''
		);
	}

	/**
	 * A post from a row of the query a point changed.
	 *
	 * @param array<array-key, mixed> $row
	 */
	public static function postOf(array $row): TopicPost {
		$nullable = static fn (string $column): ?string => isset($row[$column]) ? Markers::markup($row[$column]) : null;
		$posterId = (int) Markers::markup($row['poster_id'] ?? 0);

		return new TopicPost(
			(int) Markers::markup($row['id'] ?? 0),
			$posterId,
			Markers::markup($row['username'] ?? ''),
			Markers::markup($row['poster_ip'] ?? ''),
			$nullable('poster_email'),
			Markers::markup($row['message'] ?? ''),
			Markers::markup($row['hide_smilies'] ?? 0) === '1',
			(int) Markers::markup($row['posted'] ?? 0),
			isset($row['edited']) && Markers::markup($row['edited']) !== '' ? (int) Markers::markup($row['edited']) : null,
			$nullable('edited_by'),
			Markers::markup($row['email'] ?? ''),
			$nullable('title'),
			$nullable('url'),
			$nullable('location'),
			$nullable('signature'),
			(int) Markers::markup($row['email_setting'] ?? 0),
			(int) Markers::markup($row['num_posts'] ?? 0),
			(int) Markers::markup($row['registered'] ?? 0),
			$nullable('admin_note'),
			(int) Markers::markup($row['avatar'] ?? 0),
			(int) Markers::markup($row['avatar_width'] ?? 0),
			(int) Markers::markup($row['avatar_height'] ?? 0),
			(int) Markers::markup($row['g_id'] ?? 0),
			$nullable('g_user_title'),
			isset($row['is_online']) && (int) Markers::markup($row['is_online']) === $posterId
		);
	}
}
