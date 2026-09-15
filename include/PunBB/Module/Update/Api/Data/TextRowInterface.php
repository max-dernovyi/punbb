<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Api\Data;

/**
 * A row's text columns, by name.
 */
interface TextRowInterface {
	public function id(): int;

	/** @return list<string> the columns the row carries */
	public function columns(): array;

	/** Column $column's value; null for NULL. */
	public function value(string $column): ?string;
}
