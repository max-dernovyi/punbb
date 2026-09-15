<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\AdminIndex;

use PunBB\Module\AdminIndex\Event\PhpInfoShowing;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs ain_phpinfo_selected.
 */
final class PhpInfoObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(PhpInfoShowing $event): void {
		$this->scope->observe('ain_phpinfo_selected', $event);
	}
}
