<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Index\Api\Data\StatisticsInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * The lines of the board's statistics, named, before they are listed. Markup
 * appended goes before the list.
 */
final class StatisticsAssembling implements EventInterface {
	use MarkupEntries;

	private string $markup = '';

	/** @param array<string, string> $lines */
	public function __construct(private readonly StatisticsInterface $statistics, array $lines) {
		$this->entries = $lines;
	}

	public function statistics(): StatisticsInterface {
		return $this->statistics;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}

	private function accept(string $name): void {}
}
