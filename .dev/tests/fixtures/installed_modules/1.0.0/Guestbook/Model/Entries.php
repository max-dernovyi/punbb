<?php

declare(strict_types=1);

namespace PunBBModule\Guestbook\Model;

use PunBB\Module\Database\Sql\Connection;

final class Entries {
	public function __construct(private readonly Connection $db) {}

	public function count(): int {
		return (int) $this->db->selectValue('SELECT COUNT(g.id) FROM '.$this->db->table('guestbook').' AS g');
	}
}
