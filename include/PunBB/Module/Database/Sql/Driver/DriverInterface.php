<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Sql\Driver;

use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Database\Sql\QueryException;

/**
 * Prepared statements over one database extension's connection. A statement
 * marks each parameter with ?, bound in order: an int as an integer, a float
 * as a float, a string as text, null as NULL.
 */
interface DriverInterface {
	public function platform(): Platform;

	/**
	 * @param list<int|float|string|null> $parameters
	 * @return list<array<string, int|float|string|null>> the rows, column => value
	 * @throws QueryException the database refused the statement
	 */
	public function select(string $sql, array $parameters): array;

	/**
	 * @param list<int|float|string|null> $parameters
	 * @return int the rows the statement changed
	 * @throws QueryException the database refused the statement
	 */
	public function execute(string $sql, array $parameters): int;

	/**
	 * @return int the id the database gave the row the last INSERT on this connection stored
	 * @throws QueryException the database has none to give
	 */
	public function lastInsertId(): int;
}
