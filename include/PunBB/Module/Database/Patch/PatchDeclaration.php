<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Patch;

use Closure;
use PunBB\Module\Framework\Container\Container;

/**
 * A data patch as a module declares it. The name is what the board records
 * once the patch is applied, so it never changes after a release: '<Module>::<name>'.
 */
final readonly class PatchDeclaration {
	/**
	 * @param list<string> $dependencies the patches applied before this one, by name
	 * @param Closure(Container): DataPatchInterface $factory builds the patch, resolving its constructor arguments from the container
	 */
	public function __construct(
		public string $name,
		public array $dependencies,
		public Closure $factory
	) {}
}
