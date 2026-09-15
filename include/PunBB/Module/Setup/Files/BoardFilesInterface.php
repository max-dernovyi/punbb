<?php

declare(strict_types=1);

namespace PunBB\Module\Setup\Files;

/**
 * The files of the board: config.php, the cache, the avatars and the packs.
 */
interface BoardFilesInterface {
	public function hasConfig(): bool;

	/** Writes config.php; false when the forum root cannot be written. */
	public function writeConfig(string $contents): bool;

	/** Keeps config.php as config.old.<time>.php and writes a new one; false when either cannot be done. */
	public function replaceConfig(string $contents): bool;

	public function cacheWritable(): bool;

	/** Deletes what the board cached. */
	public function clearCache(): void;

	public function avatarsWritable(): bool;

	/** @return list<string> the names of the files in the avatar directory */
	public function avatars(): array;

	/** @return ?array{int, int} the width and the height of avatar $name, null when it is no image */
	public function avatarSize(string $name): ?array;

	/** Deletes avatar $name, when its directory can be written. */
	public function removeAvatar(string $name): void;

	/** Whether $language is a language pack the board can use. */
	public function hasLanguage(string $language): bool;

	/** Whether $style is a style the board can use. */
	public function hasStyle(string $style): bool;
}
