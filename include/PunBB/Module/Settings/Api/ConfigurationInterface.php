<?php

declare(strict_types=1);

namespace PunBB\Module\Settings\Api;

use PunBB\Module\Settings\Api\Data\SettingInterface;

/**
 * The board's settings in the config table.
 */
interface ConfigurationInterface {
	/** Stores the value of each permission, a p_ setting. */
	public function updatePermissions(SettingInterface ...$settings): void;

	/** Stores the value of each option, an o_ setting. */
	public function updateOptions(SettingInterface ...$settings): void;
}
