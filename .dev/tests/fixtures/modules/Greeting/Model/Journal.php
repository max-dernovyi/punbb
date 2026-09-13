<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Greeting\Model;

/**
 * What the fixture modules did, in the order they did it.
 */
final class Journal {
	/** @var list<string> */
	private array $entries = array();

	public function record(string $entry): void {
		$this->entries[] = $entry;
	}

	/** @return list<string> */
	public function entries(): array {
		return $this->entries;
	}
}
