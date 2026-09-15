<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Event;

use InvalidArgumentException;
use PunBB\Module\Forums\Api\Data\ForumInterface;
use PunBB\Module\Forums\Api\Data\ForumPositionInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of changing the forums, each before the browser is sent on: a forum
 * added, once its form is submitted and once it is stored; deleted, once its
 * deletion is asked for, confirmed or not, and once it is gone; the positions
 * updated, once submitted and once stored; a forum selected for editing, its
 * details saved, once submitted and once stored, and its permissions reverted,
 * once asked for and once gone.
 */
final class ForumChangeStep implements EventInterface {
	/** An addition carries the forum as submitted, before its name is checked. */
	public const ADDING = 'adding';

	public const ADDED = 'added';

	/** A deletion carries the forum's id only. */
	public const DELETING = 'deleting';

	public const DELETED = 'deleted';

	/** Reordering carries the positions as submitted, before they are checked. */
	public const REORDERING = 'reordering';

	public const REORDERED = 'reordered';

	/** Selecting carries the forum's id only, before the forum is read. */
	public const SELECTED = 'selected';

	/** Saving carries the forum as submitted, before its name and category are checked. */
	public const SAVING = 'saving';

	public const SAVED = 'saved';

	/** Reverting carries the forum as it is stored. */
	public const REVERTING = 'reverting';

	public const REVERTED = 'reverted';

	private const STEPS = array(self::ADDING, self::ADDED, self::DELETING, self::DELETED, self::REORDERING, self::REORDERED, self::SELECTED, self::SAVING, self::SAVED, self::REVERTING, self::REVERTED);

	/**
	 * @param list<ForumPositionInterface> $positions
	 * @param bool $confirmed whether a deletion was confirmed, and the forum is about to go
	 */
	public function __construct(private readonly string $step, private readonly ?ForumInterface $forum = null, private readonly array $positions = array(), private readonly bool $confirmed = false) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Changing the forums has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	/** The forum changed; null when the positions are. */
	public function forum(): ?ForumInterface {
		return $this->forum;
	}

	/** @return list<ForumPositionInterface> */
	public function positions(): array {
		return $this->positions;
	}

	public function confirmed(): bool {
		return $this->confirmed;
	}
}
