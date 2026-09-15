<?php

declare(strict_types=1);

namespace PunBB\Module\Post\View;

use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Post\Api\Data\LocationInterface;
use PunBB\Module\Post\Event\PostCheckboxesAssembling;
use PunBB\Module\Post\Event\PostRendering;

/**
 * The posting page as the template shows it, built in the order the page is
 * read: the markup placed at each position, and the numbers the form gives
 * its field groups, items and fields.
 */
final class PostView {
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
	public function __construct(private readonly LocationInterface $location, private readonly string $action, private array $values) {
		$this->positions = array_fill_keys(PostRendering::POSITIONS, new Html(''));
	}

	/** The event for $position, carrying the form's counts so far and, at the start and before the errors, the parts it may change. */
	public function rendering(string $position, ?Parts $hiddenFields = null, ?Parts $formAttributes = null, ?Parts $textOptions = null, ?Parts $errors = null): PostRendering {
		return new PostRendering($position, $this->location, $this->action, $this->groupCount, $this->itemCount, $this->fieldCount, $hiddenFields, $formAttributes, $textOptions, $errors);
	}

	/** Places what the observers of $event added, and counts on from where they left the form. */
	public function place(PostRendering $event): void {
		$this->positions[$event->position()] = new Html($event->markup());
		$this->count($event->groupCount(), $event->itemCount(), $event->fieldCount());
	}

	/**
	 * The event for the checkboxes $checkboxes, carrying the form's counts so far.
	 *
	 * @param array<string, string> $checkboxes
	 */
	public function checkboxes(array $checkboxes): PostCheckboxesAssembling {
		return new PostCheckboxesAssembling($this->location, $checkboxes, $this->groupCount, $this->itemCount, $this->fieldCount);
	}

	/** Counts on from where the observers of $event left the form. */
	public function placeCheckboxes(PostCheckboxesAssembling $event): void {
		$this->count($event->groupCount(), $event->itemCount(), $event->fieldCount());
	}

	/** The form numbers its groups and items from none again after the guest's fieldset; its fields go on. */
	public function restartGroups(): void {
		$this->groupCount = 0;
		$this->itemCount = 0;
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
