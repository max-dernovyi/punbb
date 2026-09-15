<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Site\Config\SettingsInterface;

/**
 * The board's settings as the config cache left them in $forum_config, read when asked.
 */
final class LegacySettings implements SettingsInterface {
	public function value(string $name): string {
		$config = $GLOBALS['forum_config'] ?? null;

		return is_array($config) ? Markers::markup($config[$name] ?? '') : '';
	}

	public function enabled(string $name): bool {
		return $this->value($name) === '1';
	}

	public function all(): array {
		$settings = array();
		foreach (is_array($GLOBALS['forum_config'] ?? null) ? $GLOBALS['forum_config'] : array() as $name => $value)
			$settings[(string) $name] = Markers::markup($value);

		return $settings;
	}
}
