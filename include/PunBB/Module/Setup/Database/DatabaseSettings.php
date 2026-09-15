<?php

declare(strict_types=1);

namespace PunBB\Module\Setup\Database;

/**
 * Where a board's database is and how its tables are named.
 */
final readonly class DatabaseSettings {
	/**
	 * @param string $type the driver: 'mysqli', 'mysqli_innodb', 'pgsql', 'sqlite3'
	 * @param string $name the database, or for SQLite the file below the forum root
	 * @param bool $persistent whether the connection outlives the request
	 */
	public function __construct(
		public string $type,
		public string $host,
		public string $name,
		public string $username,
		public string $password,
		public string $prefix,
		public bool $persistent = false
	) {}

	public function isMysql(): bool {
		return $this->type === 'mysqli' || $this->type === 'mysqli_innodb';
	}
}
