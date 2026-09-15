<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\Model;

use InvalidArgumentException;
use PunBB\Module\Userlist\Api\Data\MemberSearchInterface;

final readonly class MemberSearch implements MemberSearchInterface {
	public const SORTS = array('username', 'registered', 'num_posts');

	public function __construct(
		private string $username,
		private int $groupId,
		private string $sortBy,
		private bool $descending
	) {
		if (!in_array($sortBy, self::SORTS, true))
			throw new InvalidArgumentException(sprintf('Members cannot be sorted by "%s"', $sortBy));
	}

	/**
	 * The search a member list request asks for. A username counts only for a
	 * visitor who may search usernames, and '-' is none; a group below -1 is
	 * every group, and so is anything but a single value; the post
	 * count sorts only a list that shows it.
	 *
	 * @param array<mixed> $query
	 */
	public static function fromQuery(array $query, bool $searchesUsernames, bool $showsPostCount): self {
		$username = isset($query['username']) && is_string($query['username']) && $query['username'] !== '-' && $searchesUsernames ? $query['username'] : '';

		$groupId = isset($query['show_group']) && is_scalar($query['show_group']) ? intval($query['show_group']) : -1;
		if ($groupId < -1)
			$groupId = -1;

		$sortBy = isset($query['sort_by']) && is_string($query['sort_by']) && in_array($query['sort_by'], self::SORTS, true) ? $query['sort_by'] : 'username';
		if ($sortBy === 'num_posts' && !$showsPostCount)
			$sortBy = 'username';

		$descending = isset($query['sort_dir']) && is_string($query['sort_dir']) && strtoupper($query['sort_dir']) === 'DESC';

		return new self($username, $groupId, $sortBy, $descending);
	}

	public function username(): string {
		return $this->username;
	}

	public function groupId(): int {
		return $this->groupId;
	}

	public function sortBy(): string {
		return $this->sortBy;
	}

	public function descending(): bool {
		return $this->descending;
	}
}
