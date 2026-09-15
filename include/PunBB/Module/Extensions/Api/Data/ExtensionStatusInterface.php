<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Api\Data;

/**
 * Whether an installed extension is disabled.
 */
interface ExtensionStatusInterface {
	public function id(): string;

	public function isDisabled(): bool;
}
