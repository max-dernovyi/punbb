<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Manifest;

use PunBB\Module\Extensions\Api\Data\ManifestInterface;

/**
 * A manifest as it was read: the manifest when it checked out, what is wrong with it otherwise.
 */
final readonly class ManifestReading {
	/** @param list<string> $errors each markup */
	public function __construct(public ?ManifestInterface $manifest, public array $errors = array()) {}
}
