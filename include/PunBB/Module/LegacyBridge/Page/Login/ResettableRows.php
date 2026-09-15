<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Login;

use PunBB\Module\Login\Api\Data\ResettableAccountInterface;

/**
 * An account a reset key is mailed for, as a row of login.php's query.
 */
final class ResettableRows {
	/** @return array<string, mixed> */
	public static function row(ResettableAccountInterface $account): array {
		return array(
			'id'				=> $account->id(),
			'group_id'			=> $account->groupId(),
			'username'			=> $account->username(),
			'last_email_sent'	=> $account->lastEmailSent(),
		);
	}
}
