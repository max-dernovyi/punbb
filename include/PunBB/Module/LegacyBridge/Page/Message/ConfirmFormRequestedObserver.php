<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Message;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Message\Event\ConfirmFormRequested;

/**
 * Runs fn_csrf_confirm_form_start; a value its code returns lets the request through unconfirmed.
 */
final class ConfirmFormRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ConfirmFormRequested $event): void {
		if ($this->scope->observe('fn_csrf_confirm_form_start', $event) !== null)
			$event->letThrough();
	}
}
