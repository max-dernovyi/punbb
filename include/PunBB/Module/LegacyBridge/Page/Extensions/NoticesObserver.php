<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Event\NoticesRendering;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the markup points of the page showing an install's or an uninstall's notices at their positions.
 */
final class NoticesObserver {
	/** installing or not => position => point */
	public const POINTS = array(
		1	=> array(NoticesRendering::OUTPUT_START => 'aex_install_notices_output_start', NoticesRendering::END => 'aex_install_notices_end'),
		0	=> array(NoticesRendering::OUTPUT_START => 'aex_uninstall_notices_output_start', NoticesRendering::END => 'aex_uninstall_notices_end'),
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(NoticesRendering $event): void {
		$event->append($this->scope->renderObserved(self::POINTS[$event->installing() ? 1 : 0][$event->position()], $event));
	}
}
