<?php

declare(strict_types=1);

namespace PunBB\Module\Register\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Register\Api\RegistrationsInterface;

/**
 * The registrations, read from and pruned in the users table.
 */
final class Registrations implements RegistrationsInterface {
	/** The group of an account no login has verified yet. */
	private const UNVERIFIED_GROUP = 0;

	public function __construct(private readonly Connection $db) {}

	public function registrationsFrom(string $address, int $since): int {
		return (int) $this->db->selectValue('SELECT COUNT(u.id) FROM '.$this->db->table('users').' AS u WHERE u.registration_ip=? AND u.registered>?', $address, $since);
	}

	public function removeUnverified(int ...$registeredBefore): void {
		foreach ($registeredBefore as $before)
			$this->db->execute('DELETE FROM '.$this->db->table('users').' WHERE group_id=? AND activate_key IS NOT NULL AND registered < ?', self::UNVERIFIED_GROUP, $before);
	}

	public function usernamesWithEmail(string $email): array {
		return array_map(static fn (Row $row): string => $row->string('username'), $this->db->select('SELECT u.username FROM '.$this->db->table('users').' AS u WHERE u.email=?', $email));
	}
}
