<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Api\Data;

/**
 * An address a member asked for, kept until they follow the key mailed there.
 */
interface EmailActivationInterface {
	public function userId(): int;

	public function email(): string;

	public function key(): string;
}
