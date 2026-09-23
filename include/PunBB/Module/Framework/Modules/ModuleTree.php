<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Modules;

/**
 * A directory of <Name>/Module.php files, each declaring class <namespace><Name>\Module.
 * A defect in a core tree is an exception; a module of any other tree that
 * does not load is skipped.
 */
final class ModuleTree {
	public function __construct(
		public readonly string $directory,
		public readonly string $namespace,
		public readonly bool $core = false,
	) {}

	/** The forum's own modules, in include/PunBB/Module/ below $root. */
	public static function core(string $root): self {
		return new self($root.'include/PunBB/Module', 'PunBB\\Module\\', true);
	}

	/**
	 * The third-party modules an administrator installed into modules/ below
	 * $root, autoloaded through Composer's PSR-4 map of PunBBModule\.
	 *
	 * Deleting a module's directory uninstalls it and nothing more: its tables,
	 * their rows and its recorded versions stay, for the module put back to
	 * resume from.
	 */
	public static function installed(string $root): self {
		return new self($root.'modules', 'PunBBModule\\');
	}
}
