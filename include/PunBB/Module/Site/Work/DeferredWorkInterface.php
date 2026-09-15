<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Work;

use Closure;

/**
 * Work that runs once the response is delivered, so what it costs and whether
 * it fails is nothing the visitor can read.
 */
interface DeferredWorkInterface {
	/** @param Closure(): void $work */
	public function defer(Closure $work): void;
}
