<?php

declare(strict_types=1);

namespace PunBB\Module\Settings\Model;

use PunBB\Module\Settings\Api\Data\SettingInterface;

final readonly class Setting implements SettingInterface {
	public function __construct(private string $name, private ?string $value) {}

	public function name(): string {
		return $this->name;
	}

	public function value(): ?string {
		return $this->value;
	}
}
