<?php

declare(strict_types=1);

namespace PunBB\Module\Categories\View;

use PunBB\Module\Categories\Api\Data\CategoryInterface;
use PunBB\Module\Categories\Event\CategoriesRendering;
use PunBB\Module\Categories\Event\CategoryRendering;
use PunBB\Module\Layout\View\Html;

/**
 * The categories page as the template shows it, built in the order the page is
 * read: the markup placed at each position, and the numbers the forms give
 * their field groups, items and fields.
 */
final class CategoriesView {
	/** @var array<string, Html> position => markup */
	private array $positions;

	/** @var array<string, int> what the forms number => its number */
	private array $numbers = array();

	private int $groupCount = 0;

	private int $itemCount = 0;

	private int $fieldCount = 0;

	/**
	 * @param string $action the URL the forms post to
	 * @param array<string, mixed> $values what the page shows
	 */
	public function __construct(private readonly string $action, private array $values) {
		$this->positions = array_fill_keys(CategoriesRendering::POSITIONS, new Html(''));
	}

	/**
	 * The event for $position, carrying the forms' counts so far and, at the start, the hidden fields.
	 *
	 * @param array<string, string> $hiddenFields
	 */
	public function rendering(string $position, array $hiddenFields = array()): CategoriesRendering {
		return new CategoriesRendering($position, $this->action, $hiddenFields, $this->groupCount, $this->itemCount, $this->fieldCount);
	}

	/** Places what the observers of $event added, and counts on from where they left the forms. */
	public function place(CategoriesRendering $event): void {
		$this->positions[$event->position()] = new Html($event->markup());
		$this->count($event->groupCount(), $event->itemCount(), $event->fieldCount());
	}

	/** The event for $position in the fieldset of $category. */
	public function categoryRendering(string $position, CategoryInterface $category): CategoryRendering {
		return new CategoryRendering($position, $category, $this->groupCount, $this->itemCount, $this->fieldCount);
	}

	/** What the observers of $event added, counting on from where they left the form. */
	public function placeCategory(CategoryRendering $event): Html {
		$this->count($event->groupCount(), $event->itemCount(), $event->fieldCount());

		return new Html($event->markup());
	}

	public function markup(string $position): Html {
		return $this->positions[$position];
	}

	public function numberGroup(string $name): int {
		return $this->numbers[$name] = ++$this->groupCount;
	}

	public function numberItem(string $name): int {
		return $this->numbers[$name] = ++$this->itemCount;
	}

	public function numberField(string $name): int {
		return $this->numbers[$name] = ++$this->fieldCount;
	}

	/** The next form numbers its groups and items from one again, and its fields on from the form before. */
	public function restartGroupsAndItems(): void {
		$this->groupCount = $this->itemCount = 0;
	}

	/** Each category's fieldset numbers its items from one again. */
	public function restartItems(): void {
		$this->itemCount = 0;
	}

	/** Adds what the page shows once it is read, such as the categories listed. */
	public function show(string $name, mixed $value): void {
		$this->values[$name] = $value;
	}

	/** @return array<string, mixed> the template's variables */
	public function variables(): array {
		return array('positions' => $this->positions, 'numbers' => $this->numbers) + $this->values;
	}

	private function count(int $groups, int $items, int $fields): void {
		$this->groupCount = $groups;
		$this->itemCount = $items;
		$this->fieldCount = $fields;
	}
}
