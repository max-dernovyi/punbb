<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\GroupMembershipStep;

/**
 * Runs the point at each step of moving a member into another group, with the
 * member in $user and, once moved, the group in $new_group_id.
 */
final class GroupMembershipStepObserver {
	public const POINTS = array(
		GroupMembershipStep::SUBMITTED	=> 'pf_change_group_form_submitted',
		GroupMembershipStep::CHANGED	=> 'pf_change_group_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(GroupMembershipStep $event): void {
		ProfileState::publish($event->user());

		if ($event->step() === GroupMembershipStep::CHANGED)
			$GLOBALS['new_group_id'] = $event->groupId();

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
