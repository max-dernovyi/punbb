<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Moderate\Event\HostLookupStep;

/**
 * Runs the point at each step of looking up an address, with what was asked
 * for as $_get_host, and the address as $ip once it is known.
 */
final class HostLookupObserver {
	public const POINTS = array(
		HostLookupStep::SELECTED	=> 'mr_view_ip_selected',
		HostLookupStep::SHOWING		=> 'mr_view_ip_pre_output',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(HostLookupStep $event): void {
		$GLOBALS[$event->step() === HostLookupStep::SELECTED ? '_get_host' : 'ip'] = $event->address();

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
