<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * A quick search was asked for and what it takes from the request is read,
 * before it runs: an observer may change that value.
 */
final class QuickSearchSelected implements EventInterface {
	/** @param ?int $value the member, the forum or the seconds the search takes; null for a search that takes none */
	public function __construct(private readonly string $action, private ?int $value) {}

	public function action(): string {
		return $this->action;
	}

	public function value(): ?int {
		return $this->value;
	}

	public function setValue(?int $value): void {
		$this->value = $value;
	}
}
