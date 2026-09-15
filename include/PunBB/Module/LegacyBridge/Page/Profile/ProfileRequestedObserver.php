<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\ProfileRequested;

/**
 * Runs pf_start.
 */
final class ProfileRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ProfileRequested $event): void {
		$this->scope->observe('pf_start', $event);
	}
}
