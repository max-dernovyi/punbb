<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Model;

use PunBB\Module\Extensions\Api\Data\ManifestHookInterface;

final readonly class ManifestHook implements ManifestHookInterface {
	/** @param list<string> $points */
	public function __construct(private array $points, private string $code, private int $priority) {}

	public function points(): array {
		return $this->points;
	}

	public function code(): string {
		return $this->code;
	}

	public function priority(): int {
		return $this->priority;
	}
}
