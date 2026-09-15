<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Model;

use PunBB\Module\Extensions\Api\Data\ExtensionStatusInterface;

final readonly class ExtensionStatus implements ExtensionStatusInterface {
	public function __construct(private string $id, private bool $disabled) {}

	public function id(): string {
		return $this->id;
	}

	public function isDisabled(): bool {
		return $this->disabled;
	}
}
