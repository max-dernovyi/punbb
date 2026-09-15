<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Cache;

/**
 * The board's settings as the next request reads them, rebuilt once a page
 * stores one.
 */
interface ConfigCacheInterface {
	/** Rebuilds the settings from the config table as it is stored now. */
	public function rebuild(): void;
}
