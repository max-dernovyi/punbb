<?php

declare(strict_types=1);

namespace PunBB\Module\Settings\Model;

use PunBB\Module\Settings\Api\Data\SubmittedSettingsInterface;

final class SubmittedSettings implements SubmittedSettingsInterface {
	/** @param array<string, string|int> $values name => value */
	public function __construct(private array $values = array()) {}

	/**
	 * The form as a request posted it: each value trimmed, one that is not text empty.
	 *
	 * @param array<mixed> $form
	 */
	public static function fromPost(array $form): self {
		$values = array();
		foreach ($form as $name => $value)
			$values[(string) $name] = is_string($value) ? trim($value) : '';

		return new self($values);
	}

	public function names(): array {
		return array_map(strval(...), array_keys($this->values));
	}

	public function has(string $name): bool {
		return isset($this->values[$name]);
	}

	public function value(string $name): string|int|null {
		return $this->values[$name] ?? null;
	}

	public function set(string $name, string|int $value): void {
		$this->values[$name] = $value;
	}

	public function remove(string $name): void {
		unset($this->values[$name]);
	}
}
