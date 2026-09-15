<?php

declare(strict_types=1);

namespace PunBB\Module\Register\Api;

/**
 * The accounts registered so far, as a new registration checks them.
 */
interface RegistrationsInterface {
	/** How many accounts were registered from $address since $since. */
	public function registrationsFrom(string $address, int $since): int;

	/** Removes every account never verified that was registered before each of $registeredBefore. */
	public function removeUnverified(int ...$registeredBefore): void;

	/** @return list<string> the name of every account registered with $email */
	public function usernamesWithEmail(string $email): array;
}
