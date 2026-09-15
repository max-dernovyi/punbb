<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout;

/**
 * The scope header.php and footer.php ran extension code in: a page included
 * them at global scope, so a hook there reached every global by name.
 */
final class LegacyScope {
	/** What the point evaluator binds itself, and the superglobals every scope already has. */
	private const NOT_EXPOSED = array('GLOBALS', 'this', 'hook', 'exposed', '_GET', '_POST', '_COOKIE', '_FILES', '_SERVER', '_ENV', '_REQUEST', '_SESSION');

	private const NAME = '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/';

	/** Whether extension code is attached at $point, as get_hook() decides it. */
	public static function attached(string $point): bool {
		return !defined('FORUM_DISABLE_HOOKS') && isset($GLOBALS['forum_hooks']) && is_array($GLOBALS['forum_hooks']) && isset($GLOBALS['forum_hooks'][$point]);
	}

	/**
	 * $locals, then by reference every global of a name they do not take.
	 *
	 * @param array<mixed> $locals variable name => reference
	 * @return array<mixed> variable name => reference
	 */
	public static function with(array $locals): array {
		$exposed = $locals;

		foreach (array_keys($GLOBALS) as $name)
		{
			if (!is_string($name) || array_key_exists($name, $exposed) || in_array($name, self::NOT_EXPOSED, true) || preg_match(self::NAME, $name) !== 1)
				continue;

			$exposed[$name] = &$GLOBALS[$name];
		}

		return $exposed;
	}

	/**
	 * Requires a legacy PHP file as a page script required it: at global scope,
	 * so what the file and the extension code inside it create are globals.
	 */
	public static function requireGlobally(string $file): void {
		$defined = (static function (string $__file, array $__scope): array {
			extract($__scope, EXTR_REFS | EXTR_SKIP);
			unset($__scope);
			require $__file;

			return get_defined_vars();
		})($file, self::with(array()));

		foreach ($defined as $name => $value)
			if ($name !== '__file' && !array_key_exists($name, $GLOBALS))
				$GLOBALS[$name] = $value;
	}

	/**
	 * Includes a legacy PHP file — a theme's stylesheet script, a user include,
	 * a cache file — in that scope, with $locals over it.
	 *
	 * @param array<mixed> $locals variable name => reference
	 */
	public static function include(string $file, array $locals = array()): void {
		(static function (string $__file, array $__scope): void {
			extract($__scope, EXTR_REFS | EXTR_SKIP);
			include $__file;
		})($file, self::with($locals));
	}
}
