<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Groups;

use PunBB\Module\Groups\Event\DefaultGroupStep;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs the point at each step of choosing the default group, with the group as $group_id.
 */
final class DefaultGroupObserver {
	public const POINTS = array(
		DefaultGroupStep::SETTING	=> 'agr_set_default_group_form_submitted',
		DefaultGroupStep::SET		=> 'agr_set_default_group_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(DefaultGroupStep $event): void {
		$GLOBALS['group_id'] = $event->groupId();

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
