<?php

declare(strict_types=1);

namespace PunBB\Module\Install\View;

use PunBB\Module\Layout\View\Html;
use PunBB\Module\Setup\Config\BoardConfiguration;

/**
 * What the installer's two pages show.
 */
final class InstallView {
	/** The name each database type is offered by. */
	private const DATABASE_LABELS = array(
		'mysqli'		=> 'MySQL Improved',
		'mysqli_innodb'	=> 'MySQL Improved (InnoDB)',
		'pgsql'			=> 'PostgreSQL',
		'sqlite3'		=> 'SQLite3',
	);

	/**
	 * @param array<string, Html> $strings
	 * @param list<string> $languages
	 * @param list<string> $databaseTypes
	 * @return array<string, mixed> the form's variables
	 */
	public static function form(array $strings, string $version, array $languages, string $language, array $databaseTypes, string $baseUrl, bool $repository): array {
		$databases = array();
		foreach ($databaseTypes as $type)
			$databases[$type] = self::DATABASE_LABELS[$type] ?? $type;

		return array(
			's'			=> $strings,
			'title'		=> Html::format(self::string($strings, 'Install PunBB'), $version),
			'languages'	=> $languages,
			'language'	=> $language,
			'databases'	=> $databases,
			'bothMysql'	=> isset($databases['mysqli'], $databases['mysqli_innodb']),
			'baseUrl'	=> $baseUrl,
			'repository'	=> $repository,
		);
	}

	/**
	 * @param array<string, Html> $strings
	 * @param list<Html> $alerts
	 * @return array<string, mixed> the last page's variables
	 */
	public static function installed(array $strings, string $version, array $alerts, bool $written, BoardConfiguration $configuration): array {
		$index = Html::format('<a href="../index.php">%s</a>', self::string($strings, 'Go to index'));
		$database = $configuration->database;

		return array(
			's'				=> $strings,
			'title'			=> Html::format(self::string($strings, 'Install PunBB'), $version),
			'description'	=> Html::format(self::string($strings, 'Success description'), $version),
			'alerts'		=> $alerts,
			'written'		=> $written,
			'noWrite'		=> Html::format(self::string($strings, 'No write info 2'), $index),
			'installed'		=> Html::format(self::string($strings, 'Write info'), $index),
			'config'		=> array(
				'db_type'		=> $database->type,
				'db_host'		=> $database->host,
				'db_name'		=> $database->name,
				'db_username'	=> $database->username,
				'db_password'	=> $database->password,
				'db_prefix'		=> $database->prefix,
				'base_url'		=> $configuration->baseUrl ?? '',
				'cookie_name'	=> $configuration->cookieName,
			),
		);
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
