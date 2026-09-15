<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Updates;

/**
 * A hotfix the update service offers.
 */
final readonly class AvailableHotfix {
	public function __construct(public string $id, public string $title) {}
}
