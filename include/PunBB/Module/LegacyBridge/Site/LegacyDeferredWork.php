<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use Closure;
use PunBB\Module\Site\Work\DeferredWorkInterface;

/**
 * forum_defer() of include/functions.php: forum_end_page() runs the work once the response is delivered.
 */
final class LegacyDeferredWork implements DeferredWorkInterface {
	public function defer(Closure $work): void {
		\forum_defer($work);
	}
}
