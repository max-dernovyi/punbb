<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extern;

use PunBB\Module\Extern\Event\FeedAssembling;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs the point after each item is added, with the post as $cur_post or the
 * topic as $cur_topic, its text parsed, the item as $item and the feed so far
 * as $feed; and the point before the feed is written. $feed is read back.
 */
final class FeedAssemblingObserver {
	/** Kind => stage => point */
	public const POINTS = array(
		FeedAssembling::POSTS	=> array(
			FeedAssembling::ITEM		=> 'ex_modify_cur_post_item',
			FeedAssembling::COMPLETE	=> 'ex_pre_topic_output',
		),
		FeedAssembling::TOPICS	=> array(
			FeedAssembling::ITEM		=> 'ex_modify_cur_topic_item',
			FeedAssembling::COMPLETE	=> 'ex_pre_forum_output',
		),
	);

	public function __construct(private readonly PageScope $scope, private readonly KeptRows $rows) {}

	public function observe(FeedAssembling $event): void {
		$point = self::POINTS[$event->kind()][$event->stage()];
		if (!LegacyScope::attached($point))
			return;

		$feed = Feeds::feed($event);
		$GLOBALS['feed'] = $feed;

		$entry = $event->entry();
		$items = $event->items();

		if ($entry !== null && $items !== array())
		{
			$item = $items[count($items) - 1];
			$GLOBALS['item'] = Feeds::item($item);

			$row = $this->rows->row($entry) ?? Feeds::row($entry, $event->kind());
			$row['message'] = $item->description();
			if ($event->kind() === FeedAssembling::TOPICS)
				$row['subject'] = $item->title();

			$GLOBALS[$event->kind() === FeedAssembling::POSTS ? 'cur_post' : 'cur_topic'] = $row;
		}

		$this->scope->observe($point, $event);

		Feeds::readBack($GLOBALS['feed'] ?? null, $event);
	}
}
