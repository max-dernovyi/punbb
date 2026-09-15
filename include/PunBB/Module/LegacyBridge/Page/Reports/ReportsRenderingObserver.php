<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Reports;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Reports\Event\ReportsRendering;

/**
 * Renders the reports page's markup points at their positions.
 */
final class ReportsRenderingObserver {
	public const POINTS = array(
		ReportsRendering::MAIN_OUTPUT_START	=> 'arp_main_output_start',
		ReportsRendering::END				=> 'arp_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ReportsRendering $event): void {
		$event->append($this->scope->renderObserved(self::POINTS[$event->position()], $event));
	}
}
