<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page;

use SplObjectStorage;

/**
 * The rows a page's query returned, as the page script handed them to
 * extension code, kept by the object the repository answered with: an array
 * of columns each, including any column extension code added to the query.
 */
final class KeptRows {
	/** @var SplObjectStorage<object, array<array-key, mixed>> */
	private SplObjectStorage $rows;

	public function __construct() {
		$this->rows = new SplObjectStorage();
	}

	/** @param array<array-key, mixed> $row */
	public function keep(object $answer, array $row): void {
		$this->rows[$answer] = $row;
	}

	/** @return array<array-key, mixed>|null */
	public function row(object $answer): ?array {
		return $this->rows->contains($answer) ? $this->rows[$answer] : null;
	}
}
