<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Api\Data;

/**
 * A section of a member's profile as a form saved it: each column of the
 * users table the section writes, and its value.
 */
interface DetailsInterface {
	public function userId(): int;

	/** @return list<string> the columns written, in order */
	public function columns(): array;

	/** The value $column takes; null for NULL. */
	public function value(string $column): ?string;
}
