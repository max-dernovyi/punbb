<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Userlist\Api\Data\MemberSearchInterface;
use PunBB\Module\Userlist\Api\MemberDirectoryInterface;

/**
 * The members, read from the users and groups tables.
 */
final class MemberDirectory implements MemberDirectoryInterface {
	/** The group of accounts that have not confirmed their address. */
	public const UNVERIFIED_GROUP = 0;

	/** The group of the guest account, which is user 1. */
	public const GUEST_GROUP = 2;

	public function __construct(private readonly Connection $db) {}

	public function count(MemberSearchInterface $search): int {
		[$where, $parameters] = $this->where($search);

		return (int) $this->db->selectValue('SELECT COUNT(u.id) FROM '.$this->db->table('users').' AS u WHERE '.$where, ...$parameters);
	}

	public function find(MemberSearchInterface $search, int $offset, int $limit): array {
		[$where, $parameters] = $this->where($search);

		$rows = $this->db->select('SELECT u.id, u.username, u.title, u.num_posts, u.registered, g.g_id, g.g_user_title'.
			' FROM '.$this->db->table('users').' AS u LEFT JOIN '.$this->db->table('groups').' AS g ON g.g_id=u.group_id'.
			' WHERE '.$where.
			' ORDER BY u.'.$search->sortBy().($search->descending() ? ' DESC' : ' ASC').', u.id ASC LIMIT ? OFFSET ?',
			...array_merge($parameters, array($limit, $offset)));

		return array_map(static fn (Row $row): Member => new Member(
			$row->int('id'),
			$row->string('username'),
			$row->nullableString('title') ?? '',
			$row->int('num_posts'),
			$row->int('registered'),
			$row->nullableInt('g_id'),
			$row->nullableString('g_user_title')
		), $rows);
	}

	public function groups(): array {
		return array_map(static fn (Row $row): Group => new Group($row->int('g_id'), $row->string('g_title')),
			$this->db->select('SELECT g.g_id, g.g_title FROM '.$this->db->table('groups').' AS g WHERE g.g_id!=? ORDER BY g.g_id', self::GUEST_GROUP));
	}

	/** @return array{string, list<int|string>} the WHERE clause matching $search, and its parameters */
	private function where(MemberSearchInterface $search): array {
		$where = 'u.id > 1 AND u.group_id != ?';
		$parameters = array(self::UNVERIFIED_GROUP);

		if ($search->username() !== '')
		{
			$where .= ' AND u.username '.$this->db->platform()->likeIgnoringCase().' ?';
			$parameters[] = str_replace('*', '%', $search->username());
		}

		if ($search->groupId() > -1)
		{
			$where .= ' AND u.group_id = ?';
			$parameters[] = $search->groupId();
		}

		return array($where, $parameters);
	}
}
