<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Setup;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Setup\Environment\EnvironmentInterface;

/**
 * The requirements, the database types and the release of include/functions.php
 * and include/constants.php, and what PHP's settings allow.
 */
final class LegacyEnvironment implements EnvironmentInterface {
	public function requirementErrors(): array {
		return array_values(array_map(Markers::markup(...), (array) \check_php_requirements()));
	}

	public function version(): string {
		return Markers::markup(\FORUM_VERSION);
	}

	public function databaseRevision(): int {
		return (int) Markers::markup(\FORUM_DB_REVISION);
	}

	public function databaseTypes(): array {
		return array_values(array_map(Markers::markup(...), (array) \forum_available_db_types()));
	}

	public function removedDatabaseReplacement(string $type): ?string {
		$replacement = \forum_removed_db_type_replacement($type);

		return $replacement !== null ? Markers::markup($replacement) : null;
	}

	public function acceptsUploads(): bool {
		return self::enabled('file_uploads');
	}

	/** cURL, stream_socket_client() or allow_url_fopen, which get_remote_file() tries in turn. */
	public function fetchesRemoteFiles(): bool {
		return function_exists('curl_init') || function_exists('stream_socket_client') || self::enabled('allow_url_fopen');
	}

	private static function enabled(string $setting): bool {
		return in_array(strtolower((string) ini_get($setting)), array('on', 'true', '1'), true);
	}
}
