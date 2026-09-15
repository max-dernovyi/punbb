<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Site\Security\RandomKeysInterface;

/**
 * random_key() of include/functions.php, with the extension code attached to it.
 */
final class LegacyRandomKeys implements RandomKeysInterface {
	public function key(int $length, bool $readable = false, bool $hash = false): string {
		return Markers::markup(\random_key($length, $readable, $hash));
	}
}
