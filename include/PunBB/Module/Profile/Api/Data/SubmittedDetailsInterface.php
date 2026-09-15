<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Api\Data;

/**
 * What a section's form of a profile saves, by the column of the users table
 * each value is stored in: text as it arrived, or what checking the section
 * made of it. An observer may change, add or drop a value.
 */
interface SubmittedDetailsInterface {
	/** @return list<string> the columns, in the order the values were set */
	public function names(): array;

	public function has(string $name): bool;

	/** The value under $name; null when nothing is. */
	public function value(string $name): string|int|float|null;

	public function set(string $name, string|int|float $value): void;

	public function remove(string $name): void;
}
