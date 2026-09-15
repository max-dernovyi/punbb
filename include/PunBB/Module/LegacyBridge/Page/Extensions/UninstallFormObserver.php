<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Event\UninstallFormRendering;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the uninstall form's markup points at their positions.
 */
final class UninstallFormObserver {
	public const POINTS = array(
		UninstallFormRendering::OUTPUT_START	=> 'aex_uninstall_output_start',
		UninstallFormRendering::END				=> 'aex_uninstall_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(UninstallFormRendering $event): void {
		$event->append($this->scope->renderObserved(self::POINTS[$event->position()], $event));
	}
}
