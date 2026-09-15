<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\View;

use PunBB\Module\Groups\Event\GroupFormRendering;
use PunBB\Module\Groups\Event\GroupRemovalRendering;
use PunBB\Module\Groups\Event\GroupRowAssembling;
use PunBB\Module\Groups\Event\GroupsRendering;
use PunBB\Module\Layout\View\Html;

/**
 * A page of the groups page as the template shows it, built in the order the
 * page is read: the markup placed at each position, and the numbers the forms
 * give their field groups, items and fields.
 */
final class FormView {
	/** @var array<string, Html> position => markup */
	private array $positions;

	/** @var array<string, int> what the forms number => its number */
	private array $numbers = array();

	private int $groupCount = 0;

	private int $itemCount = 0;

	private int $fieldCount = 0;

	/**
	 * @param list<string> $positions every position of the page
	 * @param array<string, mixed> $values what the page shows
	 */
	public function __construct(array $positions, private array $values) {
		$this->positions = array_fill_keys($positions, new Html(''));
	}

	/** @return array{int, int, int} the group, item and field counts so far */
	public function counts(): array {
		return array($this->groupCount, $this->itemCount, $this->fieldCount);
	}

	/** Places what the observers of $event added, and counts on from where they left the form. */
	public function place(GroupsRendering|GroupFormRendering|GroupRemovalRendering $event): Html {
		$this->count($event->groupCount(), $event->itemCount(), $event->fieldCount());

		return $this->positions[$event->position()] = new Html($event->markup());
	}

	/** What the observers of $event added around a listed group, counting on from where they left the list. */
	public function placeRow(GroupRowAssembling $event): Html {
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

	/** A part of the form numbering its groups and items from one again. */
	public function restartGroupsAndItems(): void {
		$this->groupCount = $this->itemCount = 0;
	}

	/** Adds what the page shows once it is read, such as the groups listed. */
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
