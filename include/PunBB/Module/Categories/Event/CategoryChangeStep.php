<?php

declare(strict_types=1);

namespace PunBB\Module\Categories\Event;

use InvalidArgumentException;
use PunBB\Module\Categories\Api\Data\CategoryInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of changing the categories: a category added, once its form is
 * submitted with a name and once it is stored; a category deleted, once its
 * form is submitted, confirmed or not, and once it is gone with its forums;
 * the categories updated, once their form is submitted and once the changed
 * ones are stored. Each step before the browser is sent back to the list.
 */
final class CategoryChangeStep implements EventInterface {
	public const ADDING = 'adding';

	public const ADDED = 'added';

	/** A deletion carries the category's id only. */
	public const DELETING = 'deleting';

	public const DELETED = 'deleted';

	/** An update carries every category as submitted, checked or not. */
	public const UPDATING = 'updating';

	public const UPDATED = 'updated';

	private const STEPS = array(self::ADDING, self::ADDED, self::DELETING, self::DELETED, self::UPDATING, self::UPDATED);

	/**
	 * @param list<CategoryInterface> $categories
	 * @param bool $confirmed whether a deletion was confirmed, and the category is about to go
	 */
	public function __construct(private readonly string $step, private readonly array $categories, private readonly bool $confirmed = false) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Changing the categories has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	/** @return list<CategoryInterface> */
	public function categories(): array {
		return $this->categories;
	}

	public function confirmed(): bool {
		return $this->confirmed;
	}
}
