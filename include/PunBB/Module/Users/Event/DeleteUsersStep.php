<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of deleting users: once some are selected, before the selection is
 * read; once the deletion is confirmed; and once they are gone, before the
 * browser is sent back to the search form.
 */
final class DeleteUsersStep implements EventInterface {
	public const SELECTED = 'selected';

	public const CONFIRMED = 'confirmed';

	public const DELETED = 'deleted';

	/**
	 * @param list<int> $ids the users selected; none while the selection is not read yet
	 * @param bool $withPosts whether their posts go with them
	 */
	public function __construct(private readonly string $step, private readonly array $ids = array(), private readonly bool $withPosts = false) {
		if (!in_array($step, array(self::SELECTED, self::CONFIRMED, self::DELETED), true))
			throw new InvalidArgumentException(sprintf('Deleting users has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	/** @return list<int> */
	public function ids(): array {
		return $this->ids;
	}

	public function withPosts(): bool {
		return $this->withPosts;
	}
}
