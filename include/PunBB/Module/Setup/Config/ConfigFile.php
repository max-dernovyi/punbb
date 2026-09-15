<?php

declare(strict_types=1);

namespace PunBB\Module\Setup\Config;

/**
 * The source of a config.php.
 */
final class ConfigFile {
	/** The options config.php offers commented out, each by its description. */
	private const OPTIONS = array(
		'FORUM_DEBUG'							=> 'Enable DEBUG mode',
		'FORUM_SHOW_QUERIES'					=> 'Enable show DB Queries mode',
		'FORUM_ENABLE_IDNA'						=> 'Enable forum IDNA support',
		'FORUM_DISABLE_CSRF_CONFIRM'			=> 'Disable forum CSRF checking',
		'FORUM_DISABLE_HOOKS'					=> 'Disable forum hooks (extensions)',
		'FORUM_DISABLE_BUFFERING'				=> 'Disable forum output buffering',
		'FORUM_DISABLE_EXTENSIONS_VERSION_CHECK'	=> 'Disable forum extensions version check',
	);

	/** config.php for a board just installed at $baseUrl, its options commented out. */
	public static function installed(BoardConfiguration $configuration): string {
		$source = self::source($configuration);

		foreach (self::OPTIONS as $constant => $description)
			$source .= "\n\n// ".$description." by removing // from the following line\n//define('".$constant."', 1);";

		return $source;
	}

	/** config.php as $configuration says, for a board an update moved its address into it. */
	public static function updated(BoardConfiguration $configuration): string {
		return self::source($configuration);
	}

	/**
	 * The login cookie carries the account's password hash, so it must not be
	 * sent in the clear on a forum that is reachable over HTTPS.
	 */
	public static function isSecureAddress(string $baseUrl): bool {
		return stripos($baseUrl, 'https://') === 0;
	}

	private static function source(BoardConfiguration $configuration): string {
		$database = $configuration->database;

		return "<?php\n\n".
			self::assignment('db_type', $database->type).
			self::assignment('db_host', $database->host).
			self::assignment('db_name', $database->name).
			self::assignment('db_username', $database->username).
			self::assignment('db_password', $database->password).
			self::assignment('db_prefix', $database->prefix).
			'$p_connect = '.($database->persistent ? 'true' : 'false').";\n\n".
			self::assignment('base_url', $configuration->baseUrl ?? '')."\n".
			self::assignment('cookie_name', $configuration->cookieName).
			self::assignment('cookie_domain', $configuration->cookieDomain).
			self::assignment('cookie_path', $configuration->cookiePath).
			'$cookie_secure = '.($configuration->cookieSecure ? 1 : 0).";\n\n".
			"define('FORUM', 1);";
	}

	/** A value as a string literal PHP reads back as it was: addslashes() also escaped a double quote, which a single-quoted literal keeps. */
	private static function assignment(string $variable, string $value): string {
		return '$'.$variable.' = '.var_export($value, true).";\n";
	}
}
