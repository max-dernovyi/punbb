<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Security;

/**
 * How the board stores a password and checks one against what it stored.
 */
interface PasswordsInterface {
	/** $password as the board stores it now. */
	public function hash(string $password): string;

	/** Whether $password is the one $hash stores, with $salt for a hash in an older format. */
	public function verify(string $password, string $hash, string $salt): bool;

	/** Whether $password is the one the signed-in visitor's account stores. */
	public function verifyVisitor(string $password): bool;

	/**
	 * Checks $password against a hash no password matches, so a form costs the
	 * same whether or not the account it names exists.
	 */
	public function verifyAgainstNobody(string $password): void;

	/** Whether $hash is in an older format, to be stored again while the password is at hand. */
	public function needsRehash(string $hash): bool;

	/** How long a mailed password-reset key stays usable, in seconds: a second mail inside it is not sent. */
	public function resetKeyLifetime(): int;
}
