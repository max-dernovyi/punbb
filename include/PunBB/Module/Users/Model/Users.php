<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Users\Api\Data\FoundUserInterface;
use PunBB\Module\Users\Api\Data\ListedGroupInterface;
use PunBB\Module\Users\Api\Data\UserBanInterface;
use PunBB\Module\Users\Api\Data\UserSearchInterface;
use PunBB\Module\Users\Api\UsersInterface;

/**
 * The users, read from the users table with their groups and the addresses
 * their posts carry, with the bans they get written to the bans table.
 */
final class Users implements UsersInterface {
	private const FOUND_COLUMNS = 'u.id, u.username, u.email, u.title, u.num_posts, u.admin_note, g.g_id, g.g_user_title';

	public function __construct(private readonly Connection $db) {}

	public function addressesOf(int $userId): array {
		return array_map(static fn (Row $row): AddressUse => new AddressUse($row->nullableString('poster_ip') ?? '', $row->int('last_used'), $row->int('used_times')),
			$this->db->select('SELECT p.poster_ip, MAX(p.posted) AS last_used, COUNT(p.id) AS used_times FROM '.$this->db->table('posts').' AS p WHERE p.poster_id=? GROUP BY p.poster_ip ORDER BY last_used DESC', $userId));
	}

	public function postersFrom(string $address): array {
		return array_map(static fn (Row $row): Poster => new Poster($row->int('poster_id'), $row->string('poster')),
			$this->db->select('SELECT DISTINCT p.poster_id, p.poster FROM '.$this->db->table('posts').' AS p WHERE p.poster_ip=? ORDER BY p.poster DESC', $address));
	}

	public function member(int $id): ?FoundUserInterface {
		$row = $this->db->selectRow('SELECT '.self::FOUND_COLUMNS.' FROM '.$this->db->table('users').' AS u INNER JOIN '.$this->db->table('groups').' AS g ON g.g_id=u.group_id WHERE u.id>1 AND u.id=?', $id);

		return $row !== null ? self::found($row) : null;
	}

	public function count(UserSearchInterface $search): int {
		[$where, $parameters] = $this->where($search);

		return (int) $this->db->selectValue('SELECT COUNT(u.id) FROM '.$this->db->table('users').' AS u LEFT JOIN '.$this->db->table('groups').' AS g ON g.g_id=u.group_id WHERE '.$where, ...$parameters);
	}

	public function find(UserSearchInterface $search, int $offset, int $limit): array {
		[$where, $parameters] = $this->where($search);

		return array_map(self::found(...), $this->db->select('SELECT '.self::FOUND_COLUMNS.' FROM '.$this->db->table('users').' AS u LEFT JOIN '.$this->db->table('groups').' AS g ON g.g_id=u.group_id'.
			' WHERE '.$where.' ORDER BY u.'.$search->orderBy().($search->descending() ? ' DESC' : ' ASC').' LIMIT ? OFFSET ?', ...array_merge($parameters, array($limit, $offset))));
	}

	public function searchGroups(): array {
		return $this->groups();
	}

	public function includesAdministrators(int ...$ids): bool {
		if ($ids === array())
			return false;

		return (int) $this->db->selectValue('SELECT COUNT(u.id) FROM '.$this->db->table('users').' AS u WHERE u.id IN ('.self::list($ids).') AND u.group_id=?', ListedGroupInterface::ADMINISTRATORS) > 0;
	}

	public function postAddresses(int ...$ids): array {
		if ($ids === array())
			return array();

		return array_map(static fn (Row $row): PostAddress => new PostAddress($row->int('poster_id'), $row->nullableString('poster_ip') ?? ''),
			$this->db->select('SELECT p.poster_id, p.poster_ip FROM '.$this->db->table('posts').' AS p WHERE p.poster_id IN ('.self::list($ids).') AND p.poster_id>1 ORDER BY p.posted ASC'));
	}

	public function banTargets(int ...$ids): array {
		if ($ids === array())
			return array();

		return array_map(static fn (Row $row): BanTarget => new BanTarget($row->int('id'), $row->string('username'), $row->string('email'), $row->string('registration_ip')),
			$this->db->select('SELECT u.id, u.username, u.email, u.registration_ip FROM '.$this->db->table('users').' AS u WHERE u.id IN ('.self::list($ids).') AND u.id>1'));
	}

	public function ban(UserBanInterface ...$bans): void {
		foreach ($bans as $ban)
			$this->db->execute('INSERT INTO '.$this->db->table('bans').' (username, ip, email, message, expire, ban_creator) VALUES (?, ?, ?, ?, ?, ?)',
				$ban->username(), $ban->ip(), $ban->email(), $ban->message(), $ban->expire(), $ban->creatorId());
	}

	public function groupModerates(int $id): ?bool {
		$row = $this->db->selectRow('SELECT g.g_moderator FROM '.$this->db->table('groups').' AS g WHERE g.g_id=?', $id);

		return $row !== null ? $row->int('g_moderator') === 1 : null;
	}

	public function moveToGroup(int $groupId, int ...$ids): void {
		if ($ids !== array())
			$this->db->execute('UPDATE '.$this->db->table('users').' SET group_id=? WHERE id IN ('.self::list($ids).') AND id>1', $groupId);
	}

	public function moveTargets(): array {
		return $this->groups();
	}

	/** @return list<ListedGroup> */
	private function groups(): array {
		return array_map(static fn (Row $row): ListedGroup => new ListedGroup($row->int('g_id'), $row->string('g_title')),
			$this->db->select('SELECT g.g_id, g.g_title FROM '.$this->db->table('groups').' AS g WHERE g.g_id!=? ORDER BY g.g_title', ListedGroupInterface::GUESTS));
	}

	/** @return array{string, list<int|string>} the WHERE clause matching $search, and its parameters */
	private function where(UserSearchInterface $search): array {
		$where = array('u.id>1');
		$parameters = array();

		$bounds = array(
			'u.last_post>'	=> $search->lastPostAfter(),
			'u.last_post<'	=> $search->lastPostBefore(),
			'u.registered>'	=> $search->registeredAfter(),
			'u.registered<'	=> $search->registeredBefore(),
		);

		foreach ($bounds as $condition => $bound)
		{
			if ($bound !== null)
			{
				$where[] = $condition.'?';
				$parameters[] = $bound;
			}
		}

		foreach ($search->fields() as $field)
		{
			$where[] = 'u.'.$field->field().' '.$this->db->platform()->likeIgnoringCase().' ?';
			$parameters[] = str_replace('*', '%', $field->text());
		}

		foreach (array('u.num_posts>' => $search->postsMoreThan(), 'u.num_posts<' => $search->postsLessThan()) as $condition => $bound)
		{
			if ($bound !== null)
			{
				$where[] = $condition.'?';
				$parameters[] = $bound;
			}
		}

		if ($search->groupId() > -1)
		{
			$where[] = 'u.group_id=?';
			$parameters[] = $search->groupId();
		}

		return array(implode(' AND ', $where), $parameters);
	}

	/**
	 * An id list for IN(): integers written into the statement, as a list of
	 * users posted can be longer than a statement takes parameters.
	 *
	 * @param array<int> $ids
	 */
	private static function list(array $ids): string {
		return implode(',', array_map(intval(...), $ids));
	}

	private static function found(Row $row): FoundUser {
		return new FoundUser(
			$row->int('id'),
			$row->string('username'),
			$row->string('email'),
			$row->nullableString('title') ?? '',
			$row->int('num_posts'),
			$row->nullableString('admin_note') ?? '',
			$row->nullableInt('g_id'),
			$row->nullableString('g_user_title')
		);
	}
}
