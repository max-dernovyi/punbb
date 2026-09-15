<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Moderate\Event\ModerateActionRequested;

/**
 * Runs mr_new_action. Code there that answers a request of its own writes its
 * answer and exits, as it did.
 */
final class ModerateActionObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ModerateActionRequested $event): void {
		$this->scope->observe('mr_new_action', $event);
	}
}
