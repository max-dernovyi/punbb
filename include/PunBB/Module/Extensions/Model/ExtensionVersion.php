<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Model;

use PunBB\Module\Extensions\Api\Data\ExtensionVersionInterface;

final readonly class ExtensionVersion implements ExtensionVersionInterface {
	public function __construct(private string $id, private string $version) {}

	public function id(): string {
		return $this->id;
	}

	public function version(): string {
		return $this->version;
	}
}
