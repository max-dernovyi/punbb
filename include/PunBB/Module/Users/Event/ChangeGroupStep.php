<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of moving users into another group: once some are selected, before
 * the selection is read; once the group is submitted, before it is checked;
 * and once they are moved, before the browser is sent back to the search form.
 */
final class ChangeGroupStep implements EventInterface {
	public const SELECTED = 'selected';

	public const SUBMITTED = 'submitted';

	public const CHANGED = 'changed';

	/**
	 * @param list<int> $ids the users selected; none while the selection is not read yet
	 * @param int $groupId the group they move to; 0 while it is not submitted
	 */
	public function __construct(private readonly string $step, private readonly array $ids = array(), private readonly int $groupId = 0) {
		if (!in_array($step, array(self::SELECTED, self::SUBMITTED, self::CHANGED), true))
			throw new InvalidArgumentException(sprintf('Changing users\' group has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	/** @return list<int> */
	public function ids(): array {
		return $this->ids;
	}

	public function groupId(): int {
		return $this->groupId;
	}
}
