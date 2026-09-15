<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\UserDeletionStep;

/**
 * Runs the point at each step of deleting a member from their profile, with the member in $user.
 */
final class UserDeletionStepObserver {
	public const POINTS = array(
		UserDeletionStep::SELECTED	=> 'pf_delete_user_selected',
		UserDeletionStep::SUBMITTED	=> 'pf_delete_user_form_submitted',
		UserDeletionStep::DELETED	=> 'pf_delete_user_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(UserDeletionStep $event): void {
		ProfileState::publish($event->user());

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
