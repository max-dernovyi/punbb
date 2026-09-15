<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Chrome;

/**
 * Who the page is shown to, as far as the chrome cares.
 */
final readonly class Viewer {
	public function __construct(
		public int $id,
		public string $username,
		public bool $isGuest,
		public bool $isAdministrator,
		public bool $isModerating,
		public bool $canReadBoard,
		public bool $canSearch,
		public string $language,
		public string $style
	) {}
}
