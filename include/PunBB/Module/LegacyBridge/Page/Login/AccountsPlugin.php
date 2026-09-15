<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Login;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Login\Api\AccountsInterface;
use PunBB\Module\Login\Api\Data\CredentialsInterface;
use PunBB\Module\Login\Api\Data\LastVisitInterface;
use PunBB\Module\Login\Api\Data\ResetKeyInterface;
use PunBB\Module\Login\Api\Data\ResettableAccountInterface;
use PunBB\Module\Login\Model\Credentials;
use PunBB\Module\Login\Model\ResettableAccount;

/**
 * The login page's account points, with the query arrays login.php built. A
 * query a point changed answers instead; a statement a point changed runs
 * instead, and the repository is handed nothing to store. The account signing
 * in is left in $user_id, $group_id, $db_password_hash and $salt, the accounts
 * of an address in $users_with_email.
 */
final class AccountsPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	public function afterCredentials(AccountsInterface $subject, ?CredentialsInterface $result, string $username): ?CredentialsInterface {
		$escaped = self::escape($username);

		$query = array(
			'SELECT'	=> 'u.id, u.group_id, u.password, u.salt',
			'FROM'		=> 'users AS u',
			'WHERE'		=> in_array($GLOBALS['db_type'] ?? '', array('mysqli', 'mysqli_innodb'), true) ? 'username=\''.$escaped.'\'' : 'LOWER(username)=LOWER(\''.$escaped.'\')'
		);

		if ($this->queries->changed('li_login_qr_get_login_data', AccountsInterface::class.'::credentials', $query))
		{
			$row = PluggedQuery::listed($query);
			$result = $row !== null ? new Credentials((int) Markers::markup($row[0] ?? 0), (int) Markers::markup($row[1] ?? 0), Markers::markup($row[2] ?? ''), Markers::markup($row[3] ?? '')) : null;
		}

		$GLOBALS['user_id'] = $result?->userId();
		$GLOBALS['group_id'] = $result?->groupId();
		$GLOBALS['db_password_hash'] = $result?->passwordHash();
		$GLOBALS['salt'] = $result?->salt();

		return $result;
	}

	/** @return list<CredentialsInterface>|null */
	public function beforeStorePassword(AccountsInterface $subject, CredentialsInterface ...$credentials): ?array {
		$kept = array();
		foreach ($credentials as $credential)
		{
			$GLOBALS['form_password_hash'] = $credential->passwordHash();
			$GLOBALS['salt'] = $credential->salt();

			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'password=\''.self::escape($credential->passwordHash()).'\', salt=\''.self::escape($credential->salt()).'\'',
				'WHERE'		=> 'id='.$credential->userId()
			);

			if ($this->queries->changed('li_login_qr_update_user_hash', AccountsInterface::class.'::storePassword', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $credential;
		}

		return count($kept) !== count($credentials) ? $kept : null;
	}

	/** @return list<int>|null */
	public function beforeActivate(AccountsInterface $subject, int $groupId, int ...$userIds): ?array {
		$kept = array();
		foreach ($userIds as $userId)
		{
			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'group_id='.$groupId,
				'WHERE'		=> 'id='.$userId
			);

			if ($this->queries->changed('li_login_qr_update_user_group', AccountsInterface::class.'::activate', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $userId;
		}

		return count($kept) !== count($userIds) ? array_merge(array($groupId), $kept) : null;
	}

	/** @return list<LastVisitInterface>|null */
	public function beforeRecordLastVisit(AccountsInterface $subject, LastVisitInterface ...$visits): ?array {
		$kept = array();
		foreach ($visits as $visit)
		{
			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'last_visit='.$visit->at(),
				'WHERE'		=> 'id='.$visit->userId()
			);

			if ($this->queries->changed('li_logout_qr_update_last_visit', AccountsInterface::class.'::recordLastVisit', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $visit;
		}

		return count($kept) !== count($visits) ? $kept : null;
	}

	/**
	 * @param list<ResettableAccountInterface> $result
	 * @return list<ResettableAccountInterface>
	 */
	public function afterResettable(AccountsInterface $subject, array $result, string $email): array {
		$query = array(
			'SELECT'	=> 'u.id, u.group_id, u.username, u.salt, u.last_email_sent',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.email=\''.self::escape($email).'\''
		);

		if ($this->queries->changed('li_forgot_pass_qr_get_user_data', AccountsInterface::class.'::resettable', $query))
			$result = array_map(static fn (array $row): ResettableAccount => new ResettableAccount(
				(int) Markers::markup($row['id'] ?? 0),
				(int) Markers::markup($row['group_id'] ?? 0),
				Markers::markup($row['username'] ?? ''),
				isset($row['last_email_sent']) && $row['last_email_sent'] !== '' ? (int) Markers::markup($row['last_email_sent']) : null
			), PluggedQuery::rows($query));

		$GLOBALS['users_with_email'] = array_map(ResettableRows::row(...), $result);

		return $result;
	}

	/** @return list<ResetKeyInterface>|null */
	public function beforeIssueResetKey(AccountsInterface $subject, ResetKeyInterface ...$keys): ?array {
		$kept = array();
		foreach ($keys as $key)
		{
			$GLOBALS['new_password_key'] = $key->key();

			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'activate_key=\''.self::escape($key->key()).'\', last_email_sent = '.$key->issuedAt(),
				'WHERE'		=> 'id='.$key->userId()
			);

			if ($this->queries->changed('li_forgot_pass_qr_set_activate_key', AccountsInterface::class.'::issueResetKey', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $key;
		}

		return count($kept) !== count($keys) ? $kept : null;
	}

	private static function escape(string $text): string {
		return Markers::markup(LegacyConnection::legacy()->escape($text));
	}
}
