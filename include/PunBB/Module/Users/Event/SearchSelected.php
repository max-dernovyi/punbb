<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A search of the users was asked for and its subject checked, before anything
 * is read: the addresses a user posted from, the users who posted from an
 * address, or the users matching the search form, once its order is checked
 * and before its criteria are.
 */
final class SearchSelected implements EventInterface {
	/** The addresses user userId() posted from. */
	public const IP_STATS = 'ip_stats';

	/** The users who posted from address(). */
	public const SHOW_USERS = 'show_users';

	/** The users the search form's criteria match. */
	public const FIND_USER = 'find_user';

	public const SEARCHES = array(self::IP_STATS, self::SHOW_USERS, self::FIND_USER);

	/** @param array<string, string> $fields the search form's text fields as submitted, trimmed */
	public function __construct(
		private readonly string $search,
		private readonly int $userId = 0,
		private readonly string $address = '',
		private readonly string $orderBy = '',
		private readonly bool $descending = false,
		private readonly array $fields = array()
	) {
		if (!in_array($search, self::SEARCHES, true))
			throw new InvalidArgumentException(sprintf('The users page has no search "%s"', $search));
	}

	public function search(): string {
		return $this->search;
	}

	/** The user whose addresses are listed; 0 for another search. */
	public function userId(): int {
		return $this->userId;
	}

	/** The address whose users are listed; '' for another search. */
	public function address(): string {
		return $this->address;
	}

	/** The column the search form orders its results by; '' for another search. */
	public function orderBy(): string {
		return $this->orderBy;
	}

	public function descending(): bool {
		return $this->descending;
	}

	/** @return list<string> the names of the search form's text fields submitted, in order */
	public function fieldNames(): array {
		return array_map(strval(...), array_keys($this->fields));
	}

	/** What the search form's text field $name was submitted with, trimmed; '' when it was not. */
	public function field(string $name): string {
		return $this->fields[$name] ?? '';
	}
}
