<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Misc;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Misc\Event\MiscActionRequested;

/**
 * Runs mi_new_action with the action as $action. Code there that answers the
 * action writes its answer and exits, as it did.
 */
final class MiscActionObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(MiscActionRequested $event): void {
		$GLOBALS['action'] = $event->action() !== '' ? $event->action() : null;

		$this->scope->observe('mi_new_action', $event);
	}
}
