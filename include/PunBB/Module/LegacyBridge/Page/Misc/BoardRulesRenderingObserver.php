<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Misc;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Misc\Event\BoardRulesRendering;

/**
 * Renders the rules page's markup points at their positions.
 */
final class BoardRulesRenderingObserver {
	public const POINTS = array(
		BoardRulesRendering::OUTPUT_START	=> 'mi_rules_output_start',
		BoardRulesRendering::END			=> 'mi_rules_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(BoardRulesRendering $event): void {
		$event->append($this->scope->renderObserved(self::POINTS[$event->position()], $event));
	}
}
