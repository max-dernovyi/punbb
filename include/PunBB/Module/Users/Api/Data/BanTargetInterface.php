<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Api\Data;

/**
 * A user about to be banned: what a ban on them names.
 */
interface BanTargetInterface {
	public function id(): int;

	public function username(): string;

	public function email(): string;

	/** The address the user registered from. */
	public function registrationIp(): string;
}
