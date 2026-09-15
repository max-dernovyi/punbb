<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Cache;

/**
 * The jump list below every page, which lists the forums by category for each
 * group. The categories, forums and groups pages rebuild it; the settings
 * clear it when the URL scheme its links are written in changes.
 */
interface QuickjumpCacheInterface {
	/** Rebuilds the list from the categories, forums and groups stored now. */
	public function rebuild(): void;

	/** Drops the list, to be built again by the first page that shows it. */
	public function clear(): void;
}
