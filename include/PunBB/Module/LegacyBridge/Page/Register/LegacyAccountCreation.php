<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Register;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Register\Api\Data\NewAccountInterface;
use PunBB\Module\Register\Creation\AccountCreationInterface;

/**
 * add_user() of include/functions.php, with the extension code attached to
 * it; the account and its id are left in $user_info and $new_uid.
 */
final class LegacyAccountCreation implements AccountCreationInterface {
	public function add(NewAccountInterface $account): int {
		$user_info = NewAccountRows::row($account);
		$new_uid = 0;

		\add_user($user_info, $new_uid);

		$GLOBALS['user_info'] = $user_info;
		$GLOBALS['new_uid'] = $new_uid;

		return (int) Markers::markup($new_uid);
	}
}
