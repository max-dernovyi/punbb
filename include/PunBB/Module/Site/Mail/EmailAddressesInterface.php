<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Mail;

/**
 * What the board accepts as an email address.
 */
interface EmailAddressesInterface {
	/** Whether $address is one address a message can be sent to. */
	public function isValid(string $address): bool;

	/** Whether $address, or its domain, is banned. */
	public function isBanned(string $address): bool;
}
