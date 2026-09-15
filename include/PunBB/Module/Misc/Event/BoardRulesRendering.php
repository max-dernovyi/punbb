<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position on the page showing the board's rules, which an observer may add markup at: before them and after them.
 */
final class BoardRulesRendering implements EventInterface {
	public const OUTPUT_START = 'output_start';

	public const END = 'end';

	private const POSITIONS = array(self::OUTPUT_START, self::END);

	private string $markup = '';

	public function __construct(private readonly string $position) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The rules page has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
