<?php

declare(strict_types=1);

namespace PunBB\Module\Setup\Config;

use PunBB\Module\Setup\Database\DatabaseSettings;

/**
 * What config.php says: the database, the board's address and its cookie.
 */
final readonly class BoardConfiguration {
	/** @param ?string $baseUrl null when config.php names none, as before 1.3 */
	public function __construct(
		public DatabaseSettings $database,
		public ?string $baseUrl,
		public string $cookieName,
		public string $cookieDomain = '',
		public string $cookiePath = '/',
		public bool $cookieSecure = false
	) {}
}
