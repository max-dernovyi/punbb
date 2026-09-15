<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Register;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Register\Api\Data\NewAccountInterface;
use PunBB\Module\Register\Model\NewAccount;

/**
 * A new account as $user_info, the array register.php handed add_user(), and
 * back: the activation key quoted for the statement, or NULL.
 */
final class NewAccountRows {
	/** @return array<string, mixed> */
	public static function row(NewAccountInterface $account): array {
		return array(
			'username'				=> $account->username(),
			'group_id'				=> $account->groupId(),
			'salt'					=> $account->salt(),
			'password'				=> $account->password(),
			'password_hash'			=> $account->passwordHash(),
			'email'					=> $account->email(),
			'email_setting'			=> $account->emailSetting(),
			'timezone'				=> $account->timezone(),
			'dst'					=> $account->dst(),
			'language'				=> $account->language(),
			'style'					=> $account->style(),
			'registered'			=> $account->registeredAt(),
			'registration_ip'		=> $account->registrationIp(),
			'activate_key'			=> $account->activationKey() !== null ? '\''.$account->activationKey().'\'' : 'NULL',
			'require_verification'	=> $account->requiresVerification(),
			'notify_admins'			=> $account->notifiesAdmins(),
		);
	}

	/** The account $row describes, what it leaves out taken from $account. */
	public static function account(mixed $row, NewAccountInterface $account): NewAccountInterface {
		if (!is_array($row))
			return $account;

		$key = Markers::markup($row['activate_key'] ?? 'NULL');

		return new NewAccount(
			Markers::markup($row['username'] ?? $account->username()),
			(int) Markers::markup($row['group_id'] ?? $account->groupId()),
			Markers::markup($row['salt'] ?? $account->salt()),
			Markers::markup($row['password'] ?? $account->password()),
			Markers::markup($row['password_hash'] ?? $account->passwordHash()),
			Markers::markup($row['email'] ?? $account->email()),
			(int) Markers::markup($row['email_setting'] ?? $account->emailSetting()),
			(float) Markers::markup($row['timezone'] ?? $account->timezone()),
			(int) Markers::markup($row['dst'] ?? $account->dst()),
			Markers::markup($row['language'] ?? $account->language()),
			Markers::markup($row['style'] ?? $account->style()),
			(int) Markers::markup($row['registered'] ?? $account->registeredAt()),
			Markers::markup($row['registration_ip'] ?? $account->registrationIp()),
			strtoupper($key) === 'NULL' ? null : trim($key, '\''),
			!empty($row['require_verification']),
			!empty($row['notify_admins'])
		);
	}
}
