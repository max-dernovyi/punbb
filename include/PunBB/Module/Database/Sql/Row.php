<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Sql;

/**
 * One row of a result set, read column by column as the type the repository
 * expects. The drivers hand back numbers as strings or as numbers; a column
 * is read the same way from either.
 */
final readonly class Row {
	/** @param array<string, int|float|string|null> $values column => value */
	public function __construct(private array $values) {}

	public function int(string $column): int {
		$value = $this->value($column);
		if ($value === null)
			throw new DatabaseException(sprintf('Column %s is NULL, not an integer', $column));

		return (int) $value;
	}

	public function nullableInt(string $column): ?int {
		$value = $this->value($column);

		return $value === null ? null : (int) $value;
	}

	public function string(string $column): string {
		$value = $this->value($column);
		if ($value === null)
			throw new DatabaseException(sprintf('Column %s is NULL, not a string', $column));

		return (string) $value;
	}

	public function nullableString(string $column): ?string {
		$value = $this->value($column);

		return $value === null ? null : (string) $value;
	}

	/** @return array<string, int|float|string|null> every column, as the driver returned it */
	public function values(): array {
		return $this->values;
	}

	private function value(string $column): int|float|string|null {
		if (!array_key_exists($column, $this->values))
			throw new DatabaseException(sprintf('The row has no column %s; it has %s', $column, implode(', ', array_keys($this->values))));

		return $this->values[$column];
	}
}
