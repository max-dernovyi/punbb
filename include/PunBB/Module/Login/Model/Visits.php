<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Login\Api\VisitsInterface;

/**
 * The visits, in the online table.
 */
final class Visits implements VisitsInterface {
	public function __construct(private readonly Connection $db) {}

	public function endGuestVisit(string ...$addresses): void {
		foreach ($addresses as $address)
			$this->db->execute('DELETE FROM '.$this->db->table('online').' WHERE ident=?', $address);
	}

	public function endVisit(int ...$userIds): void {
		foreach ($userIds as $userId)
			$this->db->execute('DELETE FROM '.$this->db->table('online').' WHERE user_id=?', $userId);
	}
}
