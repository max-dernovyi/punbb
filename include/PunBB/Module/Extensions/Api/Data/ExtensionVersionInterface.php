<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Api\Data;

/**
 * The version an enabled extension is installed at.
 */
interface ExtensionVersionInterface {
	public function id(): string;

	public function version(): string;
}
