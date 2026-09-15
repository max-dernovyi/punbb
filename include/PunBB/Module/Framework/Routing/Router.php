<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Routing;

use PunBB\Module\Framework\Modules\ModuleException;

/**
 * The route map: every path the forum serves and what serves it. A path is
 * matched exactly, as the web server matched a script to its file name.
 */
final class Router {
	/** @var array<string, Route> path => the route serving it */
	private readonly array $byPath;

	/** @param list<Route> $routes in module order, then declaration order */
	public function __construct(private readonly array $routes) {
		$byPath = array();
		foreach ($routes as $route)
		{
			foreach ($route->paths as $path)
			{
				if (isset($byPath[$path]))
					throw new ModuleException(sprintf('Modules %s and %s both route "%s"', $byPath[$path]->module, $route->module, $path));

				$byPath[$path] = $route;
			}
		}

		$this->byPath = $byPath;
	}

	public function match(string $path): ?Route {
		return $this->byPath[$path] ?? null;
	}

	/** @return list<Route> */
	public function routes(): array {
		return $this->routes;
	}
}
