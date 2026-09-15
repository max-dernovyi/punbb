<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extern;

use PunBB\Module\Extern\Event\FeedRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders a written feed's markup points at their positions, with the feed as
 * $feed and, at an item's, the item as $item.
 */
final class FeedRenderingObserver {
	public const POINTS = array(
		FeedRendering::RSS_INFO		=> 'ex_add_new_rss_info',
		FeedRendering::RSS_ITEM		=> 'ex_add_new_rss_item_info',
		FeedRendering::ATOM_INFO	=> 'ex_add_new_atom_info',
		FeedRendering::ATOM_ITEM	=> 'ex_add_new_atom_item_info',
		FeedRendering::XML_INFO		=> 'ex_add_new_xml_info',
		FeedRendering::XML_ITEM		=> 'ex_add_new_xml_item_info',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(FeedRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['feed'] = Feeds::feed($event);

		$item = $event->item();
		if ($item !== null)
			$GLOBALS['item'] = Feeds::item($item);

		$event->append($this->scope->renderObserved($point, $event));
	}
}
