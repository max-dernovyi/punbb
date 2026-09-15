<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Groups;

use PunBB\Module\Groups\Api\Data\GroupInterface;
use PunBB\Module\Groups\Event\GroupChangeStep;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs the point at each step of adding or editing a group, with a submitted
 * group in the variables admin/groups.php held it in: $is_admin_group, $title,
 * $user_title, one per permission and interval, and $group_id while editing.
 */
final class GroupChangeObserver {
	public const POINTS = array(
		GroupChangeStep::ADD_SELECTED	=> 'agr_add_group_form_submitted',
		GroupChangeStep::EDIT_SELECTED	=> 'agr_edit_group_form_submitted',
		GroupChangeStep::VALIDATED		=> 'agr_add_edit_end_validation',
		GroupChangeStep::ADDING			=> 'agr_add_add_group',
		GroupChangeStep::EDITING		=> 'agr_edit_end_edit_group',
		GroupChangeStep::SAVED			=> 'agr_add_edit_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(GroupChangeStep $event): void {
		$group = $event->group();
		if ($group !== null)
		{
			$GLOBALS['is_admin_group'] = $group->id() === GroupInterface::ADMINISTRATORS;

			foreach (GroupsRows::variables($group) as $name => $value)
				$GLOBALS[$name] = $value;

			if ($event->step() === GroupChangeStep::EDITING)
				$GLOBALS['group_id'] = $group->id();
		}

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
