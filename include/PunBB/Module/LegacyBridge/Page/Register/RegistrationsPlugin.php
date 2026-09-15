<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Register;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Register\Api\RegistrationsInterface;

/**
 * The registration page's query points, with the query arrays register.php
 * built. A query a point changed answers instead; a statement a point changed
 * runs instead, and the repository is handed nothing to remove. The accounts
 * sharing the address are left in $dupe_list.
 */
final class RegistrationsPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	public function afterRegistrationsFrom(RegistrationsInterface $subject, int $result, string $address, int $since): int {
		$query = array(
			'SELECT'	=> 'COUNT(u.id)',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.registration_ip=\''.self::escape($address).'\' AND u.registered>'.$since
		);

		if ($this->queries->changed('rg_register_qr_check_register_flood', RegistrationsInterface::class.'::registrationsFrom', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query));

		return $result;
	}

	/** @return list<int>|null */
	public function beforeRemoveUnverified(RegistrationsInterface $subject, int ...$registeredBefore): ?array {
		$kept = array();
		foreach ($registeredBefore as $before)
		{
			$query = array(
				'DELETE'	=> 'users',
				'WHERE'		=> 'group_id='.Markers::markup(\FORUM_UNVERIFIED).' AND activate_key IS NOT NULL AND registered < '.$before
			);

			if ($this->queries->changed('rg_register_qr_delete_unverified', RegistrationsInterface::class.'::removeUnverified', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $before;
		}

		return count($kept) !== count($registeredBefore) ? $kept : null;
	}

	/**
	 * @param list<string> $result
	 * @return list<string>
	 */
	public function afterUsernamesWithEmail(RegistrationsInterface $subject, array $result, string $email): array {
		$query = array(
			'SELECT'	=> 'u.username',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.email=\''.self::escape($email).'\''
		);

		if ($this->queries->changed('rg_register_qr_check_email_dupe', RegistrationsInterface::class.'::usernamesWithEmail', $query))
			$result = array_map(static fn (array $row): string => Markers::markup($row['username'] ?? ''), PluggedQuery::rows($query));

		$GLOBALS['dupe_list'] = $result;

		return $result;
	}

	private static function escape(string $text): string {
		return Markers::markup(LegacyConnection::legacy()->escape($text));
	}
}
