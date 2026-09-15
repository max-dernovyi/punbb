<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Setup;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Setup\Files\BoardFilesInterface;

/**
 * The forum root's files, forum_clear_cache() and forum_avatar_size() of
 * include/functions.php.
 */
final class LegacyBoardFiles implements BoardFilesInterface {
	private const AVATARS = 'img/avatars/';

	public function hasConfig(): bool {
		return file_exists(LegacyChromeSource::root().'config.php');
	}

	public function writeConfig(string $contents): bool {
		$root = LegacyChromeSource::root();

		return is_writable($root) && self::write($root.'config.php', $contents);
	}

	public function replaceConfig(string $contents): bool {
		$root = LegacyChromeSource::root();

		// The old config.php is kept, just in case, and put back when the new one cannot be written
		$old = $root.'config.old.'.time().'.php';
		if (!is_writable($root) || !@rename($root.'config.php', $old))
			return false;

		if (self::write($root.'config.php', $contents))
			return true;

		@rename($old, $root.'config.php');

		return false;
	}

	public function cacheWritable(): bool {
		return is_writable(self::cache());
	}

	public function clearCache(): void {
		if (!defined('FORUM_CACHE_DIR'))
			define('FORUM_CACHE_DIR', self::cache());

		\forum_clear_cache();
	}

	public function avatarsWritable(): bool {
		return is_writable(LegacyChromeSource::root().self::AVATARS);
	}

	public function avatars(): array {
		$directory = LegacyChromeSource::root().self::AVATARS;
		if (!is_dir($directory))
			return array();

		$avatars = array();
		foreach ((array) scandir($directory) as $name)
			if (is_string($name) && is_file($directory.$name))
				$avatars[] = $name;

		return $avatars;
	}

	public function avatarSize(string $name): ?array {
		$size = \forum_avatar_size(LegacyChromeSource::root().self::AVATARS.basename($name));

		return is_array($size) ? array((int) Markers::markup($size[0] ?? 0), (int) Markers::markup($size[1] ?? 0)) : null;
	}

	/** Best effort: a file that cannot be removed must not stop the update. */
	public function removeAvatar(string $name): void {
		$file = LegacyChromeSource::root().self::AVATARS.basename($name);

		if (is_writable(dirname($file)) && is_file($file))
			unlink($file);
	}

	public function hasLanguage(string $language): bool {
		return $language !== '' && !preg_match('#[\.\\\/]#', $language) && file_exists(LegacyChromeSource::root().'lang/'.$language.'/common.php');
	}

	public function hasStyle(string $style): bool {
		return $style !== '' && !preg_match('#[\.\\\/]#', $style) && file_exists(LegacyChromeSource::root().'style/'.$style.'/'.$style.'.php');
	}

	private static function cache(): string {
		return defined('FORUM_CACHE_DIR') ? Markers::markup(constant('FORUM_CACHE_DIR')) : LegacyChromeSource::root().'cache/';
	}

	/** Silent, so a failure never prints the path; a partial file is removed. */
	private static function write(string $file, string $contents): bool {
		$handle = @fopen($file, 'wb');
		if ($handle === false)
			return false;

		$written = @fwrite($handle, $contents) === strlen($contents);
		if (@fclose($handle) && $written)
			return true;

		@unlink($file);

		return false;
	}
}
