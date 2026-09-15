<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Settings;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Settings\Api\ConfigurationInterface;
use PunBB\Module\Settings\Api\Data\SettingInterface;

/**
 * The settings' statement points, with the statement admin/settings.php built
 * for each setting and the setting's name without its o_ or p_ as $key. A
 * statement a point changed runs instead, and the repository is handed no
 * setting for it.
 */
final class ConfigurationPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	/** @return list<SettingInterface>|null */
	public function beforeUpdatePermissions(ConfigurationInterface $subject, SettingInterface ...$settings): ?array {
		return $this->statements($settings, 'aop_qr_update_permission_conf', 'updatePermissions', false);
	}

	/** @return list<SettingInterface>|null */
	public function beforeUpdateOptions(ConfigurationInterface $subject, SettingInterface ...$settings): ?array {
		return $this->statements($settings, 'aop_qr_update_permission_option', 'updateOptions', true);
	}

	/**
	 * Runs $point over the statement storing each setting: a permission as an
	 * integer, an option quoted or NULL, which the point sees as $value.
	 *
	 * @param array<SettingInterface> $settings
	 * @return list<SettingInterface>|null
	 */
	private function statements(array $settings, string $point, string $method, bool $option): ?array {
		$kept = array();
		foreach ($settings as $setting)
		{
			$key = substr($setting->name(), 2);
			$input = $setting->value();
			$value = !$option ? (string) intval($input) : ($input !== null ? '\''.self::escape($input).'\'' : 'NULL');

			$query = array(
				'UPDATE'	=> 'config',
				'SET'		=> 'conf_value='.$value,
				'WHERE'		=> 'conf_name=\''.self::escape($setting->name()).'\''
			);

			$locals = array('key' => &$key, 'input' => &$input);
			if ($option)
				$locals['value'] = &$value;

			if ($this->queries->changed($point, ConfigurationInterface::class.'::'.$method, $query, $locals))
				PluggedQuery::run($query);
			else
				$kept[] = $setting;
		}

		return count($kept) !== count($settings) ? $kept : null;
	}

	private static function escape(string $text): string {
		return Markers::markup(LegacyConnection::legacy()->escape($text));
	}
}
