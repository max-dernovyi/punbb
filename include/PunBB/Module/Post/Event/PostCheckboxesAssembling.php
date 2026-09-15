<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;
use PunBB\Module\Post\Api\Data\LocationInterface;

/**
 * The posting form's checkboxes, before their fieldset is placed: each named
 * and markup — hide_smilies, subscribe — with the form's counts, which markup
 * adding a field counts in. The fieldset is left out when none remain; markup
 * appended goes before it.
 */
final class PostCheckboxesAssembling implements EventInterface {
	use MarkupEntries;

	private string $markup = '';

	/** @param array<string, string> $checkboxes */
	public function __construct(private readonly LocationInterface $location, array $checkboxes, private int $groupCount, private int $itemCount, private int $fieldCount) {
		$this->entries = $checkboxes;
	}

	public function location(): LocationInterface {
		return $this->location;
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

	private function accept(string $name): void {}
}
