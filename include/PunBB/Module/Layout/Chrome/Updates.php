<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Chrome;

/**
 * What the last update check found.
 */
final readonly class Updates {
	/** @param ?string $version a newer release, null when there is none */
	public function __construct(
		public bool $failed,
		public ?string $version,
		public bool $hotfix
	) {}
}
