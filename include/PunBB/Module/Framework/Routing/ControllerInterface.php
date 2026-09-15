<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Routing;

use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;

/**
 * Serves the paths a module routes to it.
 */
interface ControllerInterface {
	public function handle(Request $request): Response;
}
