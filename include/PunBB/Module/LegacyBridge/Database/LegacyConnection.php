<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Database;

use DBLayer;
use mysqli;
use PgSql\Connection as PgsqlConnection;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\MysqliDriver;
use PunBB\Module\Database\Sql\Driver\PgsqlDriver;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Database\Sql\QueryException;
use PunBB\Module\Layout\Chrome\ChromeException;
use SQLite3;

/**
 * The connection include/essentials.php opened, for the new core's
 * repositories: the same link, so the same transaction. A statement counts in
 * the debug footer's query total and list, and a refused one renders the
 * forum's error page, as the DBLayer drivers do, its transaction rolled back.
 */
final class LegacyConnection {
	/** The DBLayer include/essentials.php connected. */
	public static function legacy(): DBLayer {
		$db = $GLOBALS['forum_db'] ?? null;
		if (!$db instanceof DBLayer)
			throw new ChromeException('The legacy bootstrap has no connection in $forum_db');

		return $db;
	}

	public static function open(): Connection {
		$db = self::legacy();
		$link = $db->link_id;
		$driver = match (true) {
			$link instanceof mysqli				=> new MysqliDriver($link),
			$link instanceof PgsqlConnection	=> new PgsqlDriver($link),
			$link instanceof SQLite3			=> new Sqlite3Driver($link),
			default								=> throw new ChromeException('The legacy connection has no link a driver speaks'),
		};

		return new Connection($driver, is_string($db->prefix) ? $db->prefix : '',
			static function (string $sql, float $seconds) use ($db): void {
				if (defined('FORUM_SHOW_QUERIES') || defined('FORUM_DEBUG'))
					$db->saved_queries[] = array($sql, sprintf('%.5f', $seconds));

				++$db->num_queries;
			},
			static function (QueryException $e) use ($db, $driver): void {
				if (defined('FORUM_SHOW_QUERIES') || defined('FORUM_DEBUG'))
					$db->saved_queries[] = array($e->sql(), 0);

				$db->error_no = $e->getCode();
				$db->error_msg = $e->getMessage();

				// Rolled back as the drivers roll back a refused query, before the error page's close() commits it
				if ((get_object_vars($db)['in_transaction'] ?? 0) > 0)
				{
					try {
						$driver->execute('ROLLBACK', array());
					}
					catch (QueryException) {
					}

					--$db->in_transaction;
				}

				\error($e->callFile(), $e->callLine());
			});
	}
}
