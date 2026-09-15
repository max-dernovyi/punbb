<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Routing;

use Closure;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Http\Request;

/**
 * Paths a module serves with a controller.
 */
final readonly class Route {
	/**
	 * @param list<string> $paths below the forum root, matched exactly; the first is the route's own
	 * @param class-string<ControllerInterface> $controller
	 * @param Closure(Container): object $factory builds the controller, resolving its constructor arguments from the container
	 * @param bool $quiet whether a request leaves no visit behind: the online list and the visitor's last visit stay as they were
	 * @param list<string> $quietWith query parameters that make a request quiet when it carries any of them
	 * @param bool $checksOwnToken whether the controller checks the token of a POST itself, for every caller, instead of the gate every other POST goes through
	 * @param bool $setup whether the route runs before a usable configuration exists, as the installer and the updater do: no forum is booted for it
	 */
	public function __construct(
		public string $module,
		public array $paths,
		public string $controller,
		public Closure $factory,
		public bool $quiet = false,
		public array $quietWith = array(),
		public bool $checksOwnToken = false,
		public bool $setup = false
	) {}

	/** Whether $request leaves no visit behind. */
	public function isQuietFor(Request $request): bool {
		if ($this->quiet)
			return true;

		foreach ($this->quietWith as $parameter)
			if (isset($request->query[$parameter]))
				return true;

		return false;
	}
}
