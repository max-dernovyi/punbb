<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Index;

use PunBB\Module\Index\Event\IndexRendering;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the board index's markup points at their positions.
 */
final class IndexRenderingObserver {
	public const POINTS = array(
		IndexRendering::MAIN_OUTPUT_START	=> 'in_main_output_start',
		IndexRendering::END					=> 'in_end',
		IndexRendering::INFO_OUTPUT_START	=> 'in_info_output_start',
		IndexRendering::STATS_END			=> 'in_stats_end',
		IndexRendering::USERS_ONLINE_START	=> 'in_users_online_start',
		IndexRendering::NEW_ONLINE_DATA		=> 'in_new_online_data',
		IndexRendering::USERS_ONLINE_END	=> 'in_users_online_end',
		IndexRendering::INFO_END			=> 'in_info_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(IndexRendering $event): void {
		$event->append($this->scope->renderObserved(self::POINTS[$event->position()], $event));
	}
}
