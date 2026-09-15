<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Sql;

use Closure;
use PunBB\Module\Database\Sql\Driver\DriverInterface;

/**
 * The forum's database, for a repository: prepared statements with ?
 * placeholders, the forum's table names, and the platform they run on.
 *
 * Every statement run is reported to $log, every refusal to $failed before it
 * is thrown, each with the call site outside this module.
 */
final class Connection {
	private const TABLE = '/^[a-z][a-z0-9_]*$/';

	/**
	 * @param string $prefix the prefix of every table name
	 * @param ?Closure(string, float): void $log the statement and the seconds it took
	 * @param ?Closure(QueryException): void $failed
	 */
	public function __construct(
		private readonly DriverInterface $driver,
		private readonly string $prefix,
		private readonly ?Closure $log = null,
		private readonly ?Closure $failed = null
	) {}

	public function platform(): Platform {
		return $this->driver->platform();
	}

	/** What this forum's table names start with. */
	public function prefix(): string {
		return $this->prefix;
	}

	/** Table $name in this forum's database: prefixed and quoted. */
	public function table(string $name): string {
		if (preg_match(self::TABLE, $name) !== 1)
			throw new DatabaseException(sprintf('"%s" is not a table name', $name));

		$platform = $this->driver->platform();

		// The legacy pgsql layer creates tables unquoted, so PostgreSQL stores them lowercased
		return $platform->quoteIdentifier($platform === Platform::Pgsql ? strtolower($this->prefix.$name) : $this->prefix.$name);
	}

	/** @return list<Row> */
	public function select(string $sql, int|float|string|bool|null ...$parameters): array {
		$rows = array();
		foreach ($this->run($sql, $parameters, $this->driver->select(...)) as $values)
			$rows[] = new Row($values);

		return $rows;
	}

	/** The first row, null when there is none. */
	public function selectRow(string $sql, int|float|string|bool|null ...$parameters): ?Row {
		return $this->select($sql, ...$parameters)[0] ?? null;
	}

	/** The first column of the first row, null when there is no row. */
	public function selectValue(string $sql, int|float|string|bool|null ...$parameters): int|float|string|null {
		$values = $this->selectRow($sql, ...$parameters)?->values() ?? array();

		return $values !== array() ? reset($values) : null;
	}

	/** @return int the rows the statement changed */
	public function execute(string $sql, int|float|string|bool|null ...$parameters): int {
		return $this->run($sql, $parameters, $this->driver->execute(...));
	}

	/** The id the database gave the row the last INSERT on this connection stored, also one run outside this class. */
	public function lastInsertId(): int {
		try {
			return $this->driver->lastInsertId();
		}
		catch (QueryException $e) {
			throw $this->refused($e);
		}
	}

	/**
	 * @template T
	 * @param array<int|float|string|bool|null> $parameters
	 * @param Closure(string, list<int|float|string|null>): T $statement
	 * @return T
	 */
	private function run(string $sql, array $parameters, Closure $statement): mixed {
		$bound = array();
		foreach ($parameters as $parameter)
			$bound[] = is_bool($parameter) ? (int) $parameter : $parameter;

		$start = microtime(true);

		try {
			$result = $statement($sql, $bound);
		}
		catch (QueryException $e) {
			throw $this->refused($e);
		}

		if ($this->log !== null)
			($this->log)($sql, microtime(true) - $start);

		return $result;
	}

	/** $e at its call site, reported to the failure handler. */
	private function refused(QueryException $e): QueryException {
		$located = self::located($e);

		if ($this->failed !== null)
			($this->failed)($located);

		return $located;
	}

	/** $e at the first frame outside this module. */
	private static function located(QueryException $e): QueryException {
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame)
			if (isset($frame['file'], $frame['line']) && !str_starts_with($frame['file'], dirname(__DIR__).DIRECTORY_SEPARATOR))
				return $e->at($frame['file'], $frame['line']);

		return $e->at(__FILE__, __LINE__);
	}
}
