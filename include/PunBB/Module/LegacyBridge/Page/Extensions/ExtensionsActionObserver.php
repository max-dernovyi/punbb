<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Event\ExtensionsActionRequested;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs aex_new_action with the section as $section. Code there that answers a
 * section of its own renders its page and ends the request, as it did.
 */
final class ExtensionsActionObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ExtensionsActionRequested $event): void {
		$GLOBALS['section'] = $event->section() !== '' ? $event->section() : null;

		$this->scope->observe('aex_new_action', $event);
	}
}
