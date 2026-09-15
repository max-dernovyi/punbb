<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;

/**
 * A query point after the repository answered: the point gets the query array
 * the page script built, and when its code changed the query, the changed one
 * is what answers, run through query_build() as it always was.
 */
final class PluggedQuery {
	public function __construct(private readonly PageScope $scope) {}

	/**
	 * Runs $point over $query from the plugin on $method, and says whether its code changed the query.
	 *
	 * @param array<string, mixed> $query
	 * @param array<mixed> $locals what else the point sees: variable name => reference
	 */
	public function changed(string $point, string $method, array &$query, array $locals = array()): bool {
		if (!LegacyScope::attached($point))
			return false;

		$built = $query;
		$this->scope->plugged($point, $method, array('query' => &$query) + $locals);

		return $query !== $built;
	}

	/**
	 * The result of $query, as the page script ran it.
	 *
	 * @param array<string, mixed> $query
	 */
	public static function run(array $query): mixed {
		$result = LegacyConnection::legacy()->query_build($query);
		if ($result === false)
			\error(__FILE__, __LINE__);

		return $result;
	}

	/**
	 * Every row of $query, each an array of columns.
	 *
	 * @param array<string, mixed> $query
	 * @return list<array<array-key, mixed>>
	 */
	public static function rows(array $query): array {
		$db = LegacyConnection::legacy();
		$result = self::run($query);

		$rows = array();
		while (is_array($row = $db->fetch_assoc($result)))
			$rows[] = $row;

		return $rows;
	}

	/**
	 * The first row of $query by column position, as fetch_row() reads it, which
	 * a page script listed into variables; null when there is none.
	 *
	 * @param array<string, mixed> $query
	 * @return list<mixed>|null
	 */
	public static function listed(array $query): ?array {
		$row = LegacyConnection::legacy()->fetch_row(self::run($query));

		return is_array($row) ? array_values($row) : null;
	}

	/**
	 * The first column of the first row of $query.
	 *
	 * @param array<string, mixed> $query
	 */
	public static function value(array $query): mixed {
		return LegacyConnection::legacy()->result(self::run($query));
	}
}
