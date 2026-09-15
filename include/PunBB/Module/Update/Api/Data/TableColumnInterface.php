<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Api\Data;

/**
 * A column as the MySQL server describes it.
 */
interface TableColumnInterface {
	public function name(): string;

	/** Its full type: 'varchar(80)', 'int(10) unsigned'. */
	public function type(): string;

	/** Null for a column that stores no text. */
	public function collation(): ?string;

	public function nullable(): bool;

	/** Null when it has none. */
	public function default(): ?string;
}
