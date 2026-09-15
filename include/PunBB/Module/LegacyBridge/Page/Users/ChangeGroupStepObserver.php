<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Users;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Users\Event\ChangeGroupStep;

/**
 * Runs the point at each step of moving users into another group, with those
 * selected as $users once they are read and the group as $move_to_group.
 */
final class ChangeGroupStepObserver {
	public const POINTS = array(
		ChangeGroupStep::SELECTED	=> 'aus_change_group_selected',
		ChangeGroupStep::SUBMITTED	=> 'aus_change_group_form_submitted',
		ChangeGroupStep::CHANGED	=> 'aus_change_group_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ChangeGroupStep $event): void {
		if ($event->step() !== ChangeGroupStep::SELECTED)
		{
			$GLOBALS['users'] = $event->ids();
			$GLOBALS['move_to_group'] = $event->groupId();
		}

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
