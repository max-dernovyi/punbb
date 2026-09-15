<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Sql;

/**
 * The SQL dialect a connection speaks, for the few statements where the
 * supported databases differ.
 */
enum Platform {
	case Mysql;
	case Pgsql;
	case Sqlite;

	/** $name quoted as an identifier: MySQL 8 reserves words the schema uses as names, such as GROUPS and RANK. */
	public function quoteIdentifier(string $name): string {
		return $this === self::Mysql
			? '`'.str_replace('`', '``', $name).'`'
			: '"'.str_replace('"', '""', $name).'"';
	}

	/** The operator matching a LIKE pattern regardless of case: MySQL's collation and SQLite already ignore it for ASCII. */
	public function likeIgnoringCase(): string {
		return $this === self::Pgsql ? 'ILIKE' : 'LIKE';
	}
}
