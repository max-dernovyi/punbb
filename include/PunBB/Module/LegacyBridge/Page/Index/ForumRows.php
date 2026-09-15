<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Index;

use PunBB\Module\Index\Api\Data\ForumInterface;
use PunBB\Module\Index\Event\CategoryHeadAssembling;
use PunBB\Module\Index\Event\ForumRowAssembling;
use PunBB\Module\Index\Event\OnlineInfoAssembling;
use PunBB\Module\Index\Model\Moderator;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\KeptRows;

/**
 * A forum of the board index as index.php handed it to extension code: the
 * query's row as $cur_forum, and the row being built as $forum_page's item arrays.
 */
final class ForumRows {
	/** $forum_page's item array => the parts of the row it holds */
	private const ITEMS = array(
		'item_status'	=> ForumRowAssembling::PART_STATUS,
		'item_title'	=> ForumRowAssembling::PART_TITLE,
		'item_subject'	=> ForumRowAssembling::PART_SUBJECT,
		'item_mods'		=> ForumRowAssembling::PART_MODERATORS,
	);

	public function __construct(private readonly KeptRows $rows) {}

	/** Leaves the forum's row of the query in $cur_forum. */
	public function publishForum(ForumInterface $forum): void {
		$GLOBALS['cur_forum'] = $this->rows->row($forum) ?? array(
			'cid'			=> $forum->categoryId(),
			'cat_name'		=> $forum->categoryName(),
			'fid'			=> $forum->id(),
			'forum_name'	=> $forum->name(),
			'forum_desc'	=> $forum->description() !== '' ? $forum->description() : null,
			'redirect_url'	=> $forum->redirectUrl() !== '' ? $forum->redirectUrl() : null,
			'moderators'	=> Moderator::stored($forum->moderators()),
			'num_topics'	=> $forum->topicCount(),
			'num_posts'		=> $forum->postCount(),
			'last_post'		=> $forum->lastPost(),
			'last_post_id'	=> $forum->lastPostId(),
			'last_poster'	=> $forum->lastPoster(),
		);
	}

	/**
	 * $page with the row's parts in the arrays index.php built it in.
	 *
	 * @param array<mixed> $page
	 * @return array<mixed>
	 */
	public static function page(array $page, ForumRowAssembling $row): array {
		foreach (self::ITEMS as $item => $group)
			$page[$item] = self::group($row, $group);

		$page['item_mods'] = array_values($page['item_mods']);
		$page['item_body'] = array('subject' => self::group($row, ForumRowAssembling::PART_BODY_SUBJECT), 'info' => self::group($row, ForumRowAssembling::PART_BODY_INFO));
		$page['item_style'] = $row->style();

		return $page;
	}

	/**
	 * The row's parts as extension code left them in $page.
	 *
	 * @param array<mixed> $page
	 */
	public static function readBack(array $page, ForumRowAssembling $row): void {
		foreach (self::ITEMS as $item => $group)
			self::replace($row, $group, $page[$item] ?? null);

		$body = is_array($page['item_body'] ?? null) ? $page['item_body'] : array();
		self::replace($row, ForumRowAssembling::PART_BODY_SUBJECT, $body['subject'] ?? null);
		self::replace($row, ForumRowAssembling::PART_BODY_INFO, $body['info'] ?? null);

		$row->setStyle(Markers::markup($page['item_style'] ?? ''));
	}

	/** @return array<string, string> the parts of $group, by name */
	public static function group(ForumRowAssembling|CategoryHeadAssembling|OnlineInfoAssembling $event, string $group): array {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[$name] = (string) $event->entry($group, $name);

		return $parts;
	}

	/** The parts of $group replaced by what extension code left in a variable, which may be anything. */
	public static function replace(ForumRowAssembling|CategoryHeadAssembling|OnlineInfoAssembling $event, string $group, mixed $value): void {
		foreach ($event->names($group) as $name)
			$event->remove($group, $name);

		foreach (Markers::entries($value) as $name => $markup)
			$event->set($group, (string) $name, $markup);
	}
}
