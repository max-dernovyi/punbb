<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewforum;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Viewforum\Api\Data\ListedTopicInterface;
use PunBB\Module\Viewforum\Api\Data\ViewedForumInterface;
use PunBB\Module\Viewforum\Event\TopicRowAssembling;
use PunBB\Module\Viewforum\Model\ListedTopic;
use PunBB\Module\Viewforum\Model\Moderator;
use PunBB\Module\Viewforum\Model\ViewedForum;

/**
 * A forum and its topics as viewforum.php handed them to extension code: the
 * rows of its queries, with any column a query point added, and the row being
 * built as $forum_page's item arrays.
 */
final class TopicRows {
	/** $forum_page's item array => the parts of the row it holds */
	private const ITEMS = array(
		'item_status'		=> TopicRowAssembling::PART_STATUS,
		'item_title'		=> TopicRowAssembling::PART_TITLE,
		'item_title_status'	=> TopicRowAssembling::PART_TITLE_STATUS,
		'item_nav'			=> TopicRowAssembling::PART_NAV,
		'item_subject'		=> TopicRowAssembling::PART_SUBJECT,
	);

	public function __construct(private readonly KeptRows $rows) {}

	/** @param array<array-key, mixed> $row */
	public function keep(object $answer, array $row): void {
		$this->rows->keep($answer, $row);
	}

	/** @return array<array-key, mixed> the forum's row of the query, or one built from it */
	public function forum(ViewedForumInterface $forum): array {
		return $this->rows->row($forum) ?? array(
			'forum_name'	=> $forum->name(),
			'redirect_url'	=> $forum->redirectUrl() !== '' ? $forum->redirectUrl() : null,
			'moderators'	=> Moderator::stored($forum->moderators()),
			'num_topics'	=> $forum->topicCount(),
			'sort_by'		=> $forum->sortsByPosted() ? 1 : 0,
			'post_topics'	=> $forum->groupPostsTopics() !== null ? ($forum->groupPostsTopics() ? 1 : 0) : null,
			'forum_desc'	=> $forum->description() !== '' ? $forum->description() : null,
		);
	}

	/** @return array<array-key, mixed> the topic's row of the query, or one built from it */
	public function topic(ListedTopicInterface $topic, ?int $posterId = null): array {
		$row = $this->rows->row($topic);
		if ($row !== null)
			return $row;

		$row = array(
			'id'			=> $topic->id(),
			'poster'		=> $topic->poster(),
			'subject'		=> $topic->subject(),
			'posted'		=> $topic->posted(),
			'first_post_id'	=> $topic->firstPostId(),
			'last_post'		=> $topic->lastPost(),
			'last_post_id'	=> $topic->lastPostId(),
			'last_poster'	=> $topic->lastPoster(),
			'num_views'		=> $topic->viewCount(),
			'num_replies'	=> $topic->replyCount(),
			'closed'		=> $topic->isClosed() ? 1 : 0,
			'sticky'		=> $topic->isSticky() ? 1 : 0,
			'moved_to'		=> $topic->movedTo(),
		);

		if ($posterId !== null)
			$row['has_posted'] = $topic->hasPosted() ? $posterId : null;

		return $row;
	}

	/**
	 * A forum from a row of the query a point changed.
	 *
	 * @param array<array-key, mixed> $row
	 */
	public static function forumOf(int $forumId, array $row): ViewedForum {
		$postTopics = isset($row['post_topics']) ? Markers::markup($row['post_topics']) : '';

		return new ViewedForum(
			$forumId,
			Markers::markup($row['forum_name'] ?? ''),
			Markers::markup($row['forum_desc'] ?? ''),
			Markers::markup($row['redirect_url'] ?? ''),
			Moderator::listOf(isset($row['moderators']) ? Markers::markup($row['moderators']) : null),
			(int) Markers::markup($row['num_topics'] ?? 0),
			Markers::markup($row['sort_by'] ?? '') === '1',
			$postTopics !== '' ? $postTopics === '1' : null,
			isset($row['is_subscribed']) && Markers::markup($row['is_subscribed']) !== ''
		);
	}

	/**
	 * A topic from a row of the query a point changed.
	 *
	 * @param array<array-key, mixed> $row
	 */
	public static function topicOf(array $row, ?int $posterId): ListedTopic {
		return new ListedTopic(
			(int) Markers::markup($row['id'] ?? 0),
			Markers::markup($row['poster'] ?? ''),
			Markers::markup($row['subject'] ?? ''),
			(int) Markers::markup($row['posted'] ?? 0),
			(int) Markers::markup($row['first_post_id'] ?? 0),
			(int) Markers::markup($row['last_post'] ?? 0),
			(int) Markers::markup($row['last_post_id'] ?? 0),
			Markers::markup($row['last_poster'] ?? ''),
			(int) Markers::markup($row['num_views'] ?? 0),
			(int) Markers::markup($row['num_replies'] ?? 0),
			Markers::markup($row['closed'] ?? 0) === '1',
			Markers::markup($row['sticky'] ?? 0) === '1',
			isset($row['moved_to']) && Markers::markup($row['moved_to']) !== '' ? (int) Markers::markup($row['moved_to']) : null,
			$posterId !== null && isset($row['has_posted']) && (int) Markers::markup($row['has_posted']) === $posterId
		);
	}

	/**
	 * $page with the row's parts in the arrays viewforum.php built it in.
	 *
	 * @param array<mixed> $page
	 * @return array<mixed>
	 */
	public static function page(array $page, TopicRowAssembling $row): array {
		foreach (self::ITEMS as $item => $group)
			$page[$item] = self::group($row, $group);

		$page['item_body'] = array('subject' => self::group($row, TopicRowAssembling::PART_BODY_SUBJECT), 'info' => self::group($row, TopicRowAssembling::PART_BODY_INFO));
		$page['item_style'] = $row->style();

		return $page;
	}

	/**
	 * The row's parts as extension code left them in $page.
	 *
	 * @param array<mixed> $page
	 */
	public static function readBack(array $page, TopicRowAssembling $row): void {
		foreach (self::ITEMS as $item => $group)
			self::replace($row, $group, $page[$item] ?? null);

		$body = is_array($page['item_body'] ?? null) ? $page['item_body'] : array();
		self::replace($row, TopicRowAssembling::PART_BODY_SUBJECT, $body['subject'] ?? null);
		self::replace($row, TopicRowAssembling::PART_BODY_INFO, $body['info'] ?? null);

		$row->setStyle(Markers::markup($page['item_style'] ?? ''));
	}

	/** @return array<string, string> the parts of $group, by name */
	public static function group(TopicRowAssembling $event, string $group): array {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[$name] = (string) $event->entry($group, $name);

		return $parts;
	}

	/** The parts of $group replaced by what extension code left in a variable, which may be anything. */
	public static function replace(TopicRowAssembling $event, string $group, mixed $value): void {
		foreach ($event->names($group) as $name)
			$event->remove($group, $name);

		foreach (Markers::entries($value) as $name => $markup)
			$event->set($group, (string) $name, $markup);
	}

	/** @return array<string, string> */
	public static function parts(Parts $parts): array {
		$entries = array();
		foreach ($parts->names() as $name)
			$entries[$name] = (string) $parts->entry($name);

		return $entries;
	}
}
