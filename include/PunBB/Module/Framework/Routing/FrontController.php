<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Routing;

use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Modules\ModuleException;

/**
 * Serves a request with the controller of the route it matched.
 */
final class FrontController {
	public function __construct(private readonly Container $container) {}

	public function handle(Route $route, Request $request): Response {
		$controller = ($route->factory)($this->container);
		if (!$controller instanceof $route->controller)
			throw new ModuleException(sprintf('Module %s wired controller %s to a %s', $route->module, $route->controller, $controller::class));

		return $controller->handle($request);
	}
}
