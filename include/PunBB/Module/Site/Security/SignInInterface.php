<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Security;

/**
 * The login cookie, and the session that must not survive a change of who the
 * visitor is.
 */
interface SignInInterface {
	/** Replaces the session id, dropping anything planted under the old one; nothing without a running session. */
	public function regenerateSession(): void;

	/** Sends the cookie signing member $userId in until $expire, bound to their password hash and salt. */
	public function signIn(int $userId, string $passwordHash, string $salt, int $expire): void;

	/**
	 * When the login cookie among $cookies says it expires; 0 when there is none, or it says nothing.
	 *
	 * @param array<mixed> $cookies
	 */
	public function expiryOf(array $cookies): int;

	/** Sends the guest's cookie in place of a member's. */
	public function signOut(): void;
}
