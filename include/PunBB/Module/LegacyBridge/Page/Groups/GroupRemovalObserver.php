<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Groups;

use PunBB\Module\Groups\Event\GroupRemovalStep;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs the point at each step of removing a group, with the group as $group_id.
 */
final class GroupRemovalObserver {
	public const POINTS = array(
		GroupRemovalStep::SELECTED	=> 'agr_del_group_selected',
		GroupRemovalStep::REMOVING	=> 'agr_del_group_form_submitted',
		GroupRemovalStep::REMOVED	=> 'agr_del_group_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(GroupRemovalStep $event): void {
		$GLOBALS['group_id'] = $event->groupId();

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
