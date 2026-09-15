<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Cache;

/**
 * The caches the installed extensions shape.
 */
interface ExtensionCacheInterface {
	/** Empties the board's PHP cache. */
	public function clear(): void;

	/** Builds the hook cache from the enabled extensions. */
	public function rebuildHooks(): void;
}
