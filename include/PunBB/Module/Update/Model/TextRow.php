<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Model;

use PunBB\Module\Update\Api\Data\TextRowInterface;
use PunBB\Module\Update\Charset\ConversionException;

final readonly class TextRow implements TextRowInterface {
	/** @param array<string, ?string> $values column => value */
	public function __construct(private int $id, private array $values) {}

	public function id(): int {
		return $this->id;
	}

	public function columns(): array {
		return array_keys($this->values);
	}

	public function value(string $column): ?string {
		if (!array_key_exists($column, $this->values))
			throw new ConversionException(sprintf('Row %d carries no column "%s"', $this->id, $column));

		return $this->values[$column];
	}

	/** This row with column $column holding $value. */
	public function with(string $column, ?string $value): self {
		$values = $this->values;
		$values[$column] = $value;

		return new self($this->id, $values);
	}
}
