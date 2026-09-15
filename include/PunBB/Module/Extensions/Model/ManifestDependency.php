<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Model;

use PunBB\Module\Extensions\Api\Data\ManifestDependencyInterface;

final readonly class ManifestDependency implements ManifestDependencyInterface {
	public function __construct(private string $id, private string $minVersion = '') {}

	public function id(): string {
		return $this->id;
	}

	public function minVersion(): string {
		return $this->minVersion;
	}
}
