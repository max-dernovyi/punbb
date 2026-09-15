<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Api\Data;

/**
 * One option of the board's configuration.
 */
interface SettingInterface {
	public function name(): string;

	/** Null for an option without a value. */
	public function value(): ?string;
}
