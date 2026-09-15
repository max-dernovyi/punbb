<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Search\Event\SearchRequested;

/**
 * Runs se_start.
 */
final class SearchRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(SearchRequested $event): void {
		$this->scope->observe('se_start', $event);
	}
}
