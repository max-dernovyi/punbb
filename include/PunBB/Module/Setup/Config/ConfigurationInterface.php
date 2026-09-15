<?php

declare(strict_types=1);

namespace PunBB\Module\Setup\Config;

/**
 * config.php, which the updater runs on.
 */
interface ConfigurationInterface {
	/** What config.php says, loading it; null when it is missing or corrupt. */
	public function load(): ?BoardConfiguration;
}
