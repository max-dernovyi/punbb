<?php

declare(strict_types=1);

namespace PunBB\Module\Settings\Api\Data;

/**
 * The settings a section's form posted, by their name without the o_ or p_
 * the board stores them under: trimmed text as it arrived, or what validating
 * the section made of it. An observer may change, add or drop a value.
 */
interface SubmittedSettingsInterface {
	/** @return list<string> the names, in the order they were posted */
	public function names(): array;

	public function has(string $name): bool;

	/** The value under $name; null when nothing is. */
	public function value(string $name): string|int|null;

	public function set(string $name, string|int $value): void;

	public function remove(string $name): void;
}
