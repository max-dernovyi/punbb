<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Updates;

/**
 * The newest version of an installed extension its repository offers.
 */
final readonly class LatestVersion {
	public function __construct(public string $extensionId, public string $version, public string $repositoryUrl) {}
}
