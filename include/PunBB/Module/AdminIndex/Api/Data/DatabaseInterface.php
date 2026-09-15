<?php

declare(strict_types=1);

namespace PunBB\Module\AdminIndex\Api\Data;

/**
 * The database server the board runs on, and what the board's tables hold.
 */
interface DatabaseInterface {
	/** 'MySQL', 'PostgreSQL' or 'SQLite3'. */
	public function name(): string;

	public function version(): string;

	/** The rows in the board's tables; null where the server does not report it. */
	public function rows(): ?int;

	/** The bytes the board's tables and their indexes take; null where the server does not report it. */
	public function size(): ?int;
}
