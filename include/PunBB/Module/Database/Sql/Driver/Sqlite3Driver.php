<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Sql\Driver;

use Exception;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Database\Sql\QueryException;
use SQLite3;
use SQLite3Result;
use SQLite3Stmt;

/**
 * SQLite through SQLite3::prepare(). The extension refuses a statement by
 * throwing or by returning false, depending on whether exceptions are enabled
 * on the connection; both come out as a QueryException.
 */
final class Sqlite3Driver implements DriverInterface {
	public function __construct(private readonly SQLite3 $link) {}

	public function platform(): Platform {
		return Platform::Sqlite;
	}

	public function select(string $sql, array $parameters): array {
		return $this->run($sql, $parameters, static function (SQLite3Result $result): array {
			$rows = array();
			while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false)
			{
				/** @var array<string, int|float|string|null> $row */
				$rows[] = $row;
			}

			return $rows;
		});
	}

	public function execute(string $sql, array $parameters): int {
		return $this->run($sql, $parameters, fn (): int => $this->link->changes());
	}

	public function lastInsertId(): int {
		return $this->link->lastInsertRowID();
	}

	/**
	 * @template T
	 * @param list<int|float|string|null> $parameters
	 * @param \Closure(SQLite3Result): T $read
	 * @return T
	 */
	private function run(string $sql, array $parameters, \Closure $read): mixed {
		try {
			$statement = @$this->link->prepare($sql);
			if (!$statement instanceof SQLite3Stmt)
				throw $this->refused($sql);

			try {
				foreach ($parameters as $index => $parameter)
					$statement->bindValue($index + 1, $parameter, match (true) {
						is_int($parameter)		=> SQLITE3_INTEGER,
						is_float($parameter)	=> SQLITE3_FLOAT,
						$parameter === null		=> SQLITE3_NULL,
						default					=> SQLITE3_TEXT,
					});

				$result = @$statement->execute();
				if (!$result instanceof SQLite3Result)
					throw $this->refused($sql);

				try {
					return $read($result);
				}
				finally {
					$result->finalize();
				}
			}
			finally {
				$statement->close();
			}
		}
		catch (QueryException $e) {
			throw $e;
		}
		catch (Exception $e) {
			throw QueryException::refused($e->getMessage(), $this->link->lastErrorCode(), $sql);
		}
	}

	private function refused(string $sql): QueryException {
		return QueryException::refused($this->link->lastErrorMsg(), $this->link->lastErrorCode(), $sql);
	}
}
