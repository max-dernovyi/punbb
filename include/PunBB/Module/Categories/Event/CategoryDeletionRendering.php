<?php

declare(strict_types=1);

namespace PunBB\Module\Categories\Event;

use InvalidArgumentException;
use PunBB\Module\Categories\Api\Data\CategoryInterface;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * A position in the confirmation of a category's deletion, which an observer
 * may add markup at: before it and after it. At the start the form's hidden
 * fields, named, may still change.
 */
final class CategoryDeletionRendering implements EventInterface {
	use MarkupEntries;

	public const OUTPUT_START = 'output_start';

	public const END = 'end';

	private string $markup = '';

	/**
	 * @param string $action the URL the form posts to
	 * @param array<string, string> $hiddenFields
	 */
	public function __construct(private readonly string $position, private readonly CategoryInterface $category, private readonly string $action, array $hiddenFields = array()) {
		if (!in_array($position, array(self::OUTPUT_START, self::END), true))
			throw new InvalidArgumentException(sprintf('The confirmation of a category\'s deletion has no position "%s"', $position));

		$this->entries = $hiddenFields;
	}

	public function position(): string {
		return $this->position;
	}

	public function category(): CategoryInterface {
		return $this->category;
	}

	public function action(): string {
		return $this->action;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}

	private function accept(string $name): void {
		if ($this->position !== self::OUTPUT_START)
			throw new InvalidArgumentException('The confirmation\'s hidden fields are placed by its start');
	}
}
