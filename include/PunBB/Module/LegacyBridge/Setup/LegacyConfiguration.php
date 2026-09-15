<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Setup;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Setup\Config\BoardConfiguration;
use PunBB\Module\Setup\Config\ConfigurationInterface;
use PunBB\Module\Setup\Database\DatabaseSettings;

/**
 * config.php, included at global scope as include/essentials.php includes it,
 * with the defaults the updater ran on where it says nothing.
 */
final class LegacyConfiguration implements ConfigurationInterface {
	public function load(): ?BoardConfiguration {
		$file = LegacyChromeSource::root().'config.php';
		if (!file_exists($file))
			return null;

		LegacyScope::requireGlobally($file);

		// A 1.2 config.php defines PUN
		if (defined('PUN') && !defined('FORUM'))
			define('FORUM', 1);

		if (!defined('FORUM'))
			return null;

		if (!defined('FORUM_DEBUG'))
			define('FORUM_DEBUG', 1);

		if (!defined('FORUM_CACHE_DIR'))
			define('FORUM_CACHE_DIR', LegacyChromeSource::root().'cache/');

		$config = $GLOBALS;
		$cookieName = Markers::markup($config['cookie_name'] ?? '');

		return new BoardConfiguration(
			new DatabaseSettings(
				Markers::markup($config['db_type'] ?? ''),
				Markers::markup($config['db_host'] ?? ''),
				Markers::markup($config['db_name'] ?? ''),
				Markers::markup($config['db_username'] ?? ''),
				Markers::markup($config['db_password'] ?? ''),
				Markers::markup($config['db_prefix'] ?? ''),
				!empty($config['p_connect'])
			),
			isset($config['base_url']) ? Markers::markup($config['base_url']) : null,
			$cookieName !== '' ? $cookieName : 'forum_cookie',
			Markers::markup($config['cookie_domain'] ?? ''),
			Markers::markup($config['cookie_path'] ?? '/'),
			!empty($config['cookie_secure'])
		);
	}
}
