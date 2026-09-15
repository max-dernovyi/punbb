<?php

declare(strict_types=1);

namespace PunBB\Module\Edit\View;

use PunBB\Module\Edit\Api\Data\EditablePostInterface;
use PunBB\Module\Edit\Event\EditCheckboxesAssembling;
use PunBB\Module\Edit\Event\EditRendering;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\Parts;

/**
 * The edit page as the template shows it, built in the order the page is read:
 * the markup placed at each position, and the numbers the form gives its
 * field groups, items and fields.
 */
final class EditView {
	/** @var array<string, Html> position => markup */
	private array $positions;

	/** @var array<string, int> what the form numbers => its number */
	private array $numbers = array();

	private int $groupCount = 0;

	private int $itemCount = 0;

	private int $fieldCount = 0;

	/**
	 * @param string $action the URL the form posts to
	 * @param array<string, mixed> $values what the page shows
	 */
	public function __construct(private readonly EditablePostInterface $post, private readonly string $action, private array $values) {
		$this->positions = array_fill_keys(EditRendering::POSITIONS, new Html(''));
	}

	/** The event for $position, carrying the form's counts so far and, at the start, the parts it may change. */
	public function rendering(string $position, ?Parts $hiddenFields = null, ?Parts $formAttributes = null, ?Parts $textOptions = null, ?Parts $errors = null): EditRendering {
		return new EditRendering($position, $this->post, $this->action, $this->groupCount, $this->itemCount, $this->fieldCount, $hiddenFields, $formAttributes, $textOptions, $errors);
	}

	/** Places what the observers of $event added, and counts on from where they left the form. */
	public function place(EditRendering $event): void {
		$this->positions[$event->position()] = new Html($event->markup());
		$this->count($event->groupCount(), $event->itemCount(), $event->fieldCount());
	}

	/**
	 * The event for the checkboxes $checkboxes, carrying the form's counts so far.
	 *
	 * @param array<string, string> $checkboxes
	 */
	public function checkboxes(array $checkboxes): EditCheckboxesAssembling {
		return new EditCheckboxesAssembling($this->post, $checkboxes, $this->groupCount, $this->itemCount, $this->fieldCount);
	}

	/** Counts on from where the observers of $event left the form. */
	public function placeCheckboxes(EditCheckboxesAssembling $event): void {
		$this->count($event->groupCount(), $event->itemCount(), $event->fieldCount());
	}

	public function markup(string $position): Html {
		return $this->positions[$position];
	}

	public function numberGroup(string $name): void {
		$this->numbers[$name] = ++$this->groupCount;
	}

	public function numberItem(string $name): void {
		$this->numbers[$name] = ++$this->itemCount;
	}

	/** The next field's number, which markup built outside the template takes. */
	public function numberField(string $name): int {
		return $this->numbers[$name] = ++$this->fieldCount;
	}

	/** Adds what the page shows once it is read. */
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
