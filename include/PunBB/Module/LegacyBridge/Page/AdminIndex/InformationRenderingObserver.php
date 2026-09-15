<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\AdminIndex;

use PunBB\Module\AdminIndex\Event\InformationRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the administration index's markup points at their positions, with
 * the box count in $forum_page['item_count'], read back for the boxes that follow.
 */
final class InformationRenderingObserver {
	public const POINTS = array(
		InformationRendering::MAIN_OUTPUT_START	=> 'ain_main_output_start',
		InformationRendering::PRE_VERSION		=> 'ain_pre_version',
		InformationRendering::PRE_COMMUNITY		=> 'ain_pre_community',
		InformationRendering::PRE_SERVER_LOAD	=> 'ain_pre_server_load',
		InformationRendering::PRE_ENVIRONMENT	=> 'ain_pre_environment',
		InformationRendering::PRE_DATABASE		=> 'ain_pre_database',
		InformationRendering::ITEMS_END			=> 'ain_items_end',
		InformationRendering::END				=> 'ain_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(InformationRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page['item_count'] = $event->itemCount();
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved($point, $event));

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$event->count((int) Markers::markup($page['item_count'] ?? 0));
	}
}
