<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Sql\Driver;

use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use mysqli_stmt;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Database\Sql\QueryException;

/**
 * MySQL through mysqli's server-side prepared statements. Each parameter is
 * bound with its own type, so a LIMIT takes an integer. The driver refuses a
 * statement by throwing or by returning false, depending on the report mode;
 * both come out as a QueryException.
 */
final class MysqliDriver implements DriverInterface {
	public function __construct(private readonly mysqli $link) {}

	public function platform(): Platform {
		return Platform::Mysql;
	}

	public function select(string $sql, array $parameters): array {
		return $this->run($sql, $parameters, static function (mysqli_stmt $statement) use ($sql): array {
			$result = $statement->get_result();
			if (!$result instanceof mysqli_result)
				throw QueryException::refused($statement->error !== '' ? $statement->error : 'The statement returned no result set', $statement->errno, $sql);

			/** @var list<array<string, int|float|string|null>> $rows */
			$rows = $result->fetch_all(MYSQLI_ASSOC);
			$result->free();

			return $rows;
		});
	}

	public function execute(string $sql, array $parameters): int {
		return $this->run($sql, $parameters, static fn (mysqli_stmt $statement): int => (int) $statement->affected_rows);
	}

	public function lastInsertId(): int {
		return (int) $this->link->insert_id;
	}

	/**
	 * @template T
	 * @param list<int|float|string|null> $parameters
	 * @param \Closure(mysqli_stmt): T $read
	 * @return T
	 */
	private function run(string $sql, array $parameters, \Closure $read): mixed {
		try {
			$statement = $this->link->prepare($sql);
			if ($statement === false)
				throw QueryException::refused($this->link->error, $this->link->errno, $sql);

			try {
				if ($parameters !== array())
				{
					$types = '';
					foreach ($parameters as $parameter)
						$types .= is_int($parameter) ? 'i' : (is_float($parameter) ? 'd' : 's');

					$statement->bind_param($types, ...$parameters);
				}

				if (!$statement->execute())
					throw QueryException::refused($statement->error, $statement->errno, $sql);

				return $read($statement);
			}
			finally {
				$statement->close();
			}
		}
		catch (mysqli_sql_exception $e) {
			throw QueryException::refused($e->getMessage(), $e->getCode(), $sql);
		}
	}
}
