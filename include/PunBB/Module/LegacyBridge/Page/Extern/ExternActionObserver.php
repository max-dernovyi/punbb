<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extern;

use PunBB\Module\Extern\Event\ExternActionRequested;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs ex_new_action with the action as $action. Code there that answers the
 * action writes its answer and exits, as it did.
 */
final class ExternActionObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ExternActionRequested $event): void {
		$GLOBALS['action'] = $event->action();

		$this->scope->observe('ex_new_action', $event);
	}
}
