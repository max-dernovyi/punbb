<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Users;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Users\Event\BanUsersStep;

/**
 * Runs the point at each step of banning users, with those selected as $users
 * once they are read, and the ban as $ban_message and $ban_expire: as
 * submitted, then as the statements wrote them.
 */
final class BanUsersStepObserver {
	public const POINTS = array(
		BanUsersStep::SELECTED	=> 'aus_ban_users_selected',
		BanUsersStep::SUBMITTED	=> 'aus_ban_users_form_submitted',
		BanUsersStep::BANNED	=> 'aus_ban_users_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope, private readonly SelectedAction $action) {}

	public function observe(BanUsersStep $event): void {
		switch ($event->step())
		{
			case BanUsersStep::SELECTED:
				$this->action->select('aus_ban_users_qr_check_for_admins');
				break;

			case BanUsersStep::SUBMITTED:
				$GLOBALS['users'] = $event->ids();
				$GLOBALS['ban_message'] = $event->message();
				$GLOBALS['ban_expire'] = $event->expiry();
				break;

			case BanUsersStep::BANNED:
				$GLOBALS['users'] = $event->ids();
				$GLOBALS['ban_message'] = self::message($event->message());
				$GLOBALS['ban_expire'] = $event->expire() ?? 'NULL';
				break;
		}

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}

	/** The message as the ban's statement wrote it: quoted, or NULL. */
	public static function message(string $message): string {
		return $message !== '' ? '\''.Markers::markup(LegacyConnection::legacy()->escape($message)).'\'' : 'NULL';
	}
}
