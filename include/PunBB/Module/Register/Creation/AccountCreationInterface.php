<?php

declare(strict_types=1);

namespace PunBB\Module\Register\Creation;

use PunBB\Module\Register\Api\Data\NewAccountInterface;

/**
 * Stores a new account, mails the member the key that verifies it and tells
 * the mailing list. Behind it are points of its own that extension code also
 * reaches by calling add_user(): the module declares it, the bootstrap's side
 * wires it.
 */
interface AccountCreationInterface {
	/** Stores $account, and returns the id it got. */
	public function add(NewAccountInterface $account): int;
}
