<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * A position on the form confirming an install, which an observer may add
 * markup at: before it, before the errors that stop the install, each named
 * and still open to change, and after it.
 */
final class InstallFormRendering implements EventInterface {
	use MarkupEntries;

	public const OUTPUT_START = 'output_start';

	public const PRE_ERRORS = 'pre_errors';

	public const END = 'end';

	private const POSITIONS = array(self::OUTPUT_START, self::PRE_ERRORS, self::END);

	private string $markup = '';

	/** @param string $id the extension installed */
	public function __construct(private readonly string $position, private readonly string $id) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The install form has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function id(): string {
		return $this->id;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}

	private function accept(string $name): void {
		if ($this->position !== self::PRE_ERRORS)
			throw new InvalidArgumentException('The install form\'s errors change before they are shown only');
	}
}
