<?php

declare(strict_types=1);

namespace PunBB\Module\Reports\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position in the reports page, which an observer may add markup at: before
 * the lists, and after them.
 */
final class ReportsRendering implements EventInterface {
	public const MAIN_OUTPUT_START = 'main_output_start';

	/** After the lists, and after the page's script is registered. */
	public const END = 'end';

	public const POSITIONS = array(self::MAIN_OUTPUT_START, self::END);

	private string $markup = '';

	/**
	 * @param bool $unread whether the page lists unread reports; known at the end
	 * @param bool $read whether it lists reports marked read; known at the end
	 */
	public function __construct(private readonly string $position, private readonly bool $unread = false, private readonly bool $read = false) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The reports page has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function listsUnread(): bool {
		return $this->unread;
	}

	public function listsRead(): bool {
		return $this->read;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
