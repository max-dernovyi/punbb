<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Login\Api\AccountsInterface;
use PunBB\Module\Login\Api\Data\CredentialsInterface;
use PunBB\Module\Login\Api\Data\LastVisitInterface;
use PunBB\Module\Login\Api\Data\ResetKeyInterface;

/**
 * The accounts, read from and written to the users table.
 */
final class Accounts implements AccountsInterface {
	public function __construct(private readonly Connection $db) {}

	public function credentials(string $username): ?CredentialsInterface {
		// MySQL's collation already ignores case
		$where = $this->db->platform() === Platform::Mysql ? 'username=?' : 'LOWER(username)=LOWER(?)';

		$row = $this->db->selectRow('SELECT u.id, u.group_id, u.password, u.salt FROM '.$this->db->table('users').' AS u WHERE '.$where, $username);

		return $row !== null ? new Credentials($row->int('id'), $row->int('group_id'), $row->nullableString('password') ?? '', $row->nullableString('salt') ?? '') : null;
	}

	public function storePassword(CredentialsInterface ...$credentials): void {
		foreach ($credentials as $credential)
			$this->db->execute('UPDATE '.$this->db->table('users').' SET password=?, salt=? WHERE id=?', $credential->passwordHash(), $credential->salt(), $credential->userId());
	}

	public function activate(int $groupId, int ...$userIds): void {
		foreach ($userIds as $userId)
			$this->db->execute('UPDATE '.$this->db->table('users').' SET group_id=? WHERE id=?', $groupId, $userId);
	}

	public function recordLastVisit(LastVisitInterface ...$visits): void {
		foreach ($visits as $visit)
			$this->db->execute('UPDATE '.$this->db->table('users').' SET last_visit=? WHERE id=?', $visit->at(), $visit->userId());
	}

	public function resettable(string $email): array {
		return array_map(static fn (Row $row): ResettableAccount => new ResettableAccount($row->int('id'), $row->int('group_id'), $row->string('username'), $row->nullableInt('last_email_sent')),
			$this->db->select('SELECT u.id, u.group_id, u.username, u.salt, u.last_email_sent FROM '.$this->db->table('users').' AS u WHERE u.email=?', $email));
	}

	public function issueResetKey(ResetKeyInterface ...$keys): void {
		foreach ($keys as $key)
			$this->db->execute('UPDATE '.$this->db->table('users').' SET activate_key=?, last_email_sent=? WHERE id=?', $key->key(), $key->issuedAt(), $key->userId());
	}
}
