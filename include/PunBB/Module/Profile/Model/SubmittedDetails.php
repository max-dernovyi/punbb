<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Model;

use PunBB\Module\Profile\Api\Data\SubmittedDetailsInterface;

final class SubmittedDetails implements SubmittedDetailsInterface {
	/** @param array<string, string|int|float> $values column => value */
	public function __construct(private array $values = array()) {}

	public function names(): array {
		return array_map(strval(...), array_keys($this->values));
	}

	public function has(string $name): bool {
		return isset($this->values[$name]);
	}

	public function value(string $name): string|int|float|null {
		return $this->values[$name] ?? null;
	}

	public function set(string $name, string|int|float $value): void {
		$this->values[$name] = $value;
	}

	public function remove(string $name): void {
		unset($this->values[$name]);
	}
}
