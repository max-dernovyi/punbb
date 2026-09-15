<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Sql\Driver;

use PgSql\Connection as PgsqlConnection;
use PgSql\Result;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Database\Sql\QueryException;

/**
 * PostgreSQL through pg_query_params(), which numbers its parameters: each ?
 * outside a quoted string or identifier becomes $1, $2, … in turn. The
 * extension warns on a refused statement; the warning is silenced and the
 * failure read back from the connection.
 */
final class PgsqlDriver implements DriverInterface {
	public function __construct(private readonly PgsqlConnection $link) {}

	public function platform(): Platform {
		return Platform::Pgsql;
	}

	public function select(string $sql, array $parameters): array {
		$result = $this->run($sql, $parameters);

		/** @var list<array<string, string|null>> $rows */
		$rows = pg_fetch_all($result, PGSQL_ASSOC);
		pg_free_result($result);

		return $rows;
	}

	public function execute(string $sql, array $parameters): int {
		$result = $this->run($sql, $parameters);
		$changed = pg_affected_rows($result);
		pg_free_result($result);

		return $changed;
	}

	/** The value the last sequence this session advanced took, as a SERIAL column's INSERT advances it. */
	public function lastInsertId(): int {
		$result = $this->run('SELECT lastval()', array());
		$id = pg_fetch_result($result, 0, 0);
		pg_free_result($result);

		return (int) $id;
	}

	/** $sql with each ? placeholder numbered, quoted strings and identifiers left as they are. */
	public static function numbered(string $sql): string {
		$numbered = '';
		$number = 0;
		$length = strlen($sql);

		for ($i = 0; $i < $length; $i++)
		{
			$char = $sql[$i];

			if ($char === '\'' || $char === '"')
			{
				$end = $i + 1;
				while ($end < $length && $sql[$end] !== $char)
					$end++;

				$numbered .= substr($sql, $i, $end - $i + 1);
				$i = $end;
			}
			else if ($char === '?')
				$numbered .= '$'.(++$number);
			else
				$numbered .= $char;
		}

		return $numbered;
	}

	/** @param list<int|float|string|null> $parameters */
	private function run(string $sql, array $parameters): Result {
		$result = @pg_query_params($this->link, self::numbered($sql), $parameters);
		if ($result === false)
			throw QueryException::refused(pg_last_error($this->link), 0, $sql);

		return $result;
	}
}
