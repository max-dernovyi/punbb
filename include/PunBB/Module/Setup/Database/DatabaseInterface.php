<?php

declare(strict_types=1);

namespace PunBB\Module\Setup\Database;

/**
 * The connection to a board's database before the board runs on it. Once it
 * is open, the forum's connection and schema are this one.
 */
interface DatabaseInterface {
	/** Opens the database $settings names, speaking UTF-8 to it. */
	public function open(DatabaseSettings $settings): void;

	/** Opens the database $settings names without choosing a character set: setNames() chooses the one its text is stored in. */
	public function openUnencoded(DatabaseSettings $settings): void;

	/** The version the database server reports. */
	public function serverVersion(): string;

	/** Whether the MySQL server stores tables in InnoDB. */
	public function supportsInnodb(): bool;

	/** Speaks character set $charset to the database: 'utf8', 'latin1'. */
	public function setNames(string $charset): void;

	public function startTransaction(): void;

	public function endTransaction(): void;

	/** Closes the connection, when one is open, committing what it left uncommitted. */
	public function close(): void;
}
