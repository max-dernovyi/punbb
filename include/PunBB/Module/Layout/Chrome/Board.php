<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Chrome;

use PunBB\Module\Layout\View\Html;

/**
 * The board settings the chrome shows or acts on.
 */
final readonly class Board {
	/**
	 * @param ?string $version the running version, null when the board does not show it
	 * @param ?Updates $updates what the update check found, null when it is off
	 */
	public function __construct(
		public string $title,
		public string $description,
		public bool $announcement,
		public string $announcementHeading,
		public Html $announcementMessage,
		public bool $maintenance,
		public bool $quickjump,
		public ?string $version,
		public bool $reportsByEmailOnly,
		public ?Updates $updates,
		public bool $databaseIsNewer
	) {}
}
