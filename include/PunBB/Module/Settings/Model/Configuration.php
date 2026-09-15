<?php

declare(strict_types=1);

namespace PunBB\Module\Settings\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Settings\Api\ConfigurationInterface;
use PunBB\Module\Settings\Api\Data\SettingInterface;

/**
 * The settings, written to the config table.
 */
final class Configuration implements ConfigurationInterface {
	public function __construct(private readonly Connection $db) {}

	public function updatePermissions(SettingInterface ...$settings): void {
		$this->update($settings);
	}

	public function updateOptions(SettingInterface ...$settings): void {
		$this->update($settings);
	}

	/** @param array<SettingInterface> $settings */
	private function update(array $settings): void {
		foreach ($settings as $setting)
			$this->db->execute('UPDATE '.$this->db->table('config').' SET conf_value=? WHERE conf_name=?', $setting->value(), $setting->name());
	}
}
