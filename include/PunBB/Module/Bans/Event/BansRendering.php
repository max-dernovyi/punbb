<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * A position in the bans page, which an observer may add markup at: before
 * the form starting a ban and the list, and after them. At the start the
 * form's hidden fields, named, may still change, and the form numbers its
 * field group, item and field on from the counts observers leave.
 */
final class BansRendering implements EventInterface {
	use MarkupEntries;

	public const MAIN_OUTPUT_START = 'main_output_start';

	public const END = 'end';

	private string $markup = '';

	/**
	 * @param string $action the URL the form starting a ban posts to
	 * @param array<string, string> $hiddenFields
	 */
	public function __construct(
		private readonly string $position,
		private readonly string $action,
		array $hiddenFields,
		private int $groupCount = 0,
		private int $itemCount = 0,
		private int $fieldCount = 0
	) {
		if (!in_array($position, array(self::MAIN_OUTPUT_START, self::END), true))
			throw new InvalidArgumentException(sprintf('The bans page has no position "%s"', $position));

		$this->entries = $hiddenFields;
	}

	public function position(): string {
		return $this->position;
	}

	public function action(): string {
		return $this->action;
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

	private function accept(string $name): void {
		if ($this->position !== self::MAIN_OUTPUT_START)
			throw new InvalidArgumentException('The bans page\'s hidden fields are placed by its end');
	}
}
