<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Model;

use PunBB\Module\Install\Api\Data\ExtensionHookInterface;

final readonly class BundledHook implements ExtensionHookInterface {
	public function __construct(private string $point, private string $code, private int $priority, private int $installed) {}

	public function point(): string {
		return $this->point;
	}

	public function code(): string {
		return $this->code;
	}

	public function priority(): int {
		return $this->priority;
	}

	public function installed(): int {
		return $this->installed;
	}
}
