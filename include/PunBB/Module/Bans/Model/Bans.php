<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Model;

use PunBB\Module\Bans\Api\BansInterface;
use PunBB\Module\Bans\Api\Data\BanInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;

/**
 * The bans, read from and written to the bans table, with who created each.
 */
final class Bans implements BansInterface {
	public function __construct(private readonly Connection $db) {}

	public function count(): int {
		return (int) $this->db->selectValue('SELECT COUNT(b.id) FROM '.$this->db->table('bans').' AS b');
	}

	public function page(int $offset, int $limit): array {
		return array_map(self::ban(...), $this->db->select('SELECT b.id, b.username, b.ip, b.email, b.message, b.expire, b.ban_creator, u.username AS ban_creator_username'.
			' FROM '.$this->db->table('bans').' AS b LEFT JOIN '.$this->db->table('users').' AS u ON u.id=b.ban_creator'.
			' ORDER BY b.id LIMIT ? OFFSET ?', $limit, $offset));
	}

	public function find(int $id): ?BanInterface {
		$row = $this->db->selectRow('SELECT b.id, b.username, b.ip, b.email, b.message, b.expire, b.ban_creator, u.username AS ban_creator_username'.
			' FROM '.$this->db->table('bans').' AS b LEFT JOIN '.$this->db->table('users').' AS u ON u.id=b.ban_creator WHERE b.id=?', $id);

		return $row !== null ? self::ban($row) : null;
	}

	public function add(BanInterface ...$bans): void {
		foreach ($bans as $ban)
			$this->db->execute('INSERT INTO '.$this->db->table('bans').' (username, ip, email, message, expire, ban_creator) VALUES (?, ?, ?, ?, ?, ?)',
				$ban->username(), $ban->ip(), $ban->email(), $ban->message(), $ban->expire(), $ban->creatorId());
	}

	public function update(BanInterface ...$bans): void {
		foreach ($bans as $ban)
			$this->db->execute('UPDATE '.$this->db->table('bans').' SET username=?, ip=?, email=?, message=?, expire=? WHERE id=?',
				$ban->username(), $ban->ip(), $ban->email(), $ban->message(), $ban->expire(), $ban->id());
	}

	public function remove(int ...$ids): void {
		foreach ($ids as $id)
			$this->db->execute('DELETE FROM '.$this->db->table('bans').' WHERE id=?', $id);
	}

	private static function ban(Row $row): Ban {
		return new Ban(
			$row->int('id'),
			$row->nullableString('username'),
			$row->nullableString('ip'),
			$row->nullableString('email'),
			$row->nullableString('message'),
			$row->nullableInt('expire'),
			$row->nullableInt('ban_creator') ?? 0,
			$row->nullableString('ban_creator_username')
		);
	}
}
