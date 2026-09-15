<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Bans;

use PunBB\Module\Bans\Event\BanChangeStep;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs the point at each step of changing a ban, with the ban in the variables
 * admin/bans.php held it in: as submitted while it is checked, and quoted for
 * its statement once it is stored; $ban_id for a removal.
 */
final class BanChangeObserver {
	public const POINTS = array(
		BanChangeStep::SAVING	=> 'aba_add_edit_ban_form_submitted',
		BanChangeStep::SAVED	=> 'aba_add_edit_ban_pre_redirect',
		BanChangeStep::REMOVING	=> 'aba_del_ban_form_submitted',
		BanChangeStep::REMOVED	=> 'aba_del_ban_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(BanChangeStep $event): void {
		$ban = $event->ban();

		switch ($event->step())
		{
			case BanChangeStep::SAVING:
				$GLOBALS['ban_user'] = $ban->username() ?? '';
				$GLOBALS['ban_ip'] = $ban->ip() ?? '';
				$GLOBALS['ban_email'] = $ban->email() ?? '';
				$GLOBALS['ban_message'] = $ban->message() ?? '';
				$GLOBALS['ban_expire'] = $event->submittedExpire();
				break;

			case BanChangeStep::SAVED:
				$GLOBALS['ban_user'] = BanRows::quoted($ban->username());
				$GLOBALS['ban_ip'] = BanRows::quoted($ban->ip());
				$GLOBALS['ban_email'] = BanRows::quoted($ban->email());
				$GLOBALS['ban_message'] = BanRows::quoted($ban->message());
				$GLOBALS['ban_expire'] = $ban->expire() ?? 'NULL';
				break;

			default:
				$GLOBALS['ban_id'] = $ban->id();
		}

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
