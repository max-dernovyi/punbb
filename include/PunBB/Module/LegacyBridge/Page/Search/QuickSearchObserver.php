<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Search\Event\QuickSearchSelected;

/**
 * Runs se_additional_quicksearch_variables with the quick search in $action
 * and what it takes in $value, which is read back.
 */
final class QuickSearchObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(QuickSearchSelected $event): void {
		$GLOBALS['action'] = $event->action();
		$GLOBALS['value'] = $event->value();

		$this->scope->observe('se_additional_quicksearch_variables', $event);

		$value = $GLOBALS['value'] ?? null;
		$event->setValue($value !== null ? (int) Markers::markup($value) : null);
	}
}
