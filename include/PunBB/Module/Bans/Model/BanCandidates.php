<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Model;

use PunBB\Module\Bans\Api\BanCandidatesInterface;
use PunBB\Module\Bans\Api\Data\BanCandidateInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;

/**
 * The members, read from the users table, and the addresses they posted from.
 */
final class BanCandidates implements BanCandidatesInterface {
	public function __construct(private readonly Connection $db) {}

	public function byId(int $userId): ?BanCandidateInterface {
		$row = $this->db->selectRow('SELECT u.id, u.group_id, u.username, u.email, u.registration_ip FROM '.$this->db->table('users').' AS u WHERE u.id=?', $userId);

		return $row !== null ? self::candidate($row) : null;
	}

	public function byUsername(string $username): ?BanCandidateInterface {
		$row = $this->db->selectRow('SELECT u.id, u.group_id, u.username, u.email, u.registration_ip FROM '.$this->db->table('users').' AS u WHERE u.username=? AND u.id>1', $username);

		return $row !== null ? self::candidate($row) : null;
	}

	public function lastKnownIp(int $userId): ?string {
		$ip = $this->db->selectValue('SELECT p.poster_ip FROM '.$this->db->table('posts').' AS p WHERE p.poster_id=? ORDER BY p.posted DESC LIMIT 1', $userId);

		return $ip !== null ? (string) $ip : null;
	}

	private static function candidate(Row $row): BanCandidate {
		return new BanCandidate($row->int('id'), $row->int('group_id'), $row->string('username'), $row->string('email'), $row->nullableString('registration_ip') ?? '');
	}
}
