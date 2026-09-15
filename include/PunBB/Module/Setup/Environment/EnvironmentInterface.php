<?php

declare(strict_types=1);

namespace PunBB\Module\Setup\Environment;

/**
 * The release and the PHP installation it runs on.
 */
interface EnvironmentInterface {
	/** @return list<string> what this PHP installation lacks to run the forum, empty when it lacks nothing */
	public function requirementErrors(): array;

	/** The version this release installs and updates a board to. */
	public function version(): string;

	/** The revision of the database schema this release installs and updates a board to. */
	public function databaseRevision(): int;

	/** @return list<string> the supported database types whose PHP extension is present */
	public function databaseTypes(): array;

	/** The database type replacing $type, which was removed with the PHP extension it needed; null when $type was not removed. */
	public function removedDatabaseReplacement(string $type): ?string;

	/** Whether PHP accepts uploaded files, which avatars need. */
	public function acceptsUploads(): bool;

	/** Whether PHP can fetch a remote file, which the checks for updates need. */
	public function fetchesRemoteFiles(): bool;
}
