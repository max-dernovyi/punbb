<?php

declare(strict_types=1);

namespace PunBB\Module\Message\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * A position in the confirmation form, which an observer may add markup at.
 * At the start the form's hidden fields, named, may still change.
 */
final class ConfirmFormRendering implements EventInterface {
	use MarkupEntries;

	public const START = 'start';

	public const END = 'end';

	private string $markup = '';

	/** @param array<string, string> $hiddenFields */
	public function __construct(private readonly string $position, private readonly string $action, array $hiddenFields) {
		if (!in_array($position, array(self::START, self::END), true))
			throw new InvalidArgumentException(sprintf('The confirmation form has no position "%s"', $position));

		$this->entries = $hiddenFields;
	}

	public function position(): string {
		return $this->position;
	}

	/** The URL the form posts to. */
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
		if ($this->position !== self::START)
			throw new InvalidArgumentException('The confirmation form\'s hidden fields are placed by its end');
	}
}
