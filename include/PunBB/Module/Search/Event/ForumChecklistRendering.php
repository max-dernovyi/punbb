<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Search\Api\Data\SearchableForumInterface;

/**
 * A forum's box in the search form's list of forums, where an observer may add
 * markup: before the box, and its category's group when it opens one, and
 * after the box. Markup that adds fields counts them, and the list numbers on
 * from there.
 */
final class ForumChecklistRendering implements EventInterface {
	public const START = 'start';

	public const END = 'end';

	private string $markup = '';

	public function __construct(
		private readonly string $position,
		private readonly SearchableForumInterface $forum,
		private int $groupCount,
		private int $itemCount,
		private int $fieldCount
	) {
		if (!in_array($position, array(self::START, self::END), true))
			throw new InvalidArgumentException(sprintf('A forum\'s box has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function forum(): SearchableForumInterface {
		return $this->forum;
	}

	public function groupCount(): int {
		return $this->groupCount;
	}

	public function itemCount(): int {
		return $this->itemCount;
	}

	public function fieldCount(): int {
		return $this->fieldCount;
	}

	/** The form's counts once the markup added here is counted in. */
	public function count(int $groups, int $items, int $fields): void {
		$this->groupCount = $groups;
		$this->itemCount = $items;
		$this->fieldCount = $fields;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
