<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Api;

use PunBB\Module\Update\Api\Data\TableColumnInterface;
use PunBB\Module\Update\Api\Data\TextRowInterface;

/**
 * The text of a table's rows, read and stored a batch at a time, and the
 * character set MySQL stores its columns in.
 */
interface ConversionInterface {
	/** The lowest id in $table; null when it holds no row. */
	public function firstId(string $table): ?int;

	/** The lowest id of $table from $id on; null when there is none. */
	public function nextId(string $table, int $id): ?int;

	/**
	 * Columns $columns of the rows of $table by id, from id $from to before $to,
	 * or every row when no bound is given.
	 *
	 * @param list<string> $columns
	 * @return list<TextRowInterface>
	 */
	public function rows(string $table, string $idColumn, array $columns, ?int $from = null, ?int $to = null): array;

	/** Stores each value of $row into its column of the row of $table it was read from. */
	public function store(string $table, string $idColumn, TextRowInterface $row): void;

	/** @return list<TableColumnInterface> the columns of $table as the MySQL server describes them */
	public function columns(string $table): array;

	/** Makes UTF-8 the character set of what MySQL adds to $table. */
	public function setDefaultCharset(string $table): void;
}
