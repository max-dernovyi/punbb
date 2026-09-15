<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Event;

/**
 * Markup an observer adds at a position of a form, with the form's field
 * groups, items and fields numbered so far: markup that adds any of them
 * counts them, and the form numbers on from there.
 */
trait FormMarkup {
	private string $markup = '';

	private int $groupCount = 0;

	private int $itemCount = 0;

	private int $fieldCount = 0;

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
