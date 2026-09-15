<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Users;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Users\Event\DeleteUsersStep;

/**
 * Runs the point at each step of deleting users, with those selected as $users once they are read.
 */
final class DeleteUsersStepObserver {
	public const POINTS = array(
		DeleteUsersStep::SELECTED	=> 'aus_delete_users_selected',
		DeleteUsersStep::CONFIRMED	=> 'aus_delete_users_form_submitted',
		DeleteUsersStep::DELETED	=> 'aus_delete_users_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope, private readonly SelectedAction $action) {}

	public function observe(DeleteUsersStep $event): void {
		if ($event->step() === DeleteUsersStep::SELECTED)
			$this->action->select('aus_delete_users_qr_check_for_admins');
		else
			$GLOBALS['users'] = $event->ids();

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
