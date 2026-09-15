<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Site\Security\PasswordsInterface;

/**
 * forum_password_hash(), forum_password_verify() and
 * forum_password_needs_rehash() of include/functions.php, the dummy hash and
 * the reset key's lifetime include/constants.php defines.
 */
final class LegacyPasswords implements PasswordsInterface {
	public function hash(string $password): string {
		return Markers::markup(\forum_password_hash($password));
	}

	public function verify(string $password, string $hash, string $salt): bool {
		return (bool) \forum_password_verify($password, $hash, $salt);
	}

	public function verifyVisitor(string $password): bool {
		$user = is_array($GLOBALS['forum_user'] ?? null) ? $GLOBALS['forum_user'] : array();

		return (bool) \forum_password_verify($password, Markers::markup($user['password'] ?? ''), Markers::markup($user['salt'] ?? ''));
	}

	public function verifyAgainstNobody(string $password): void {
		\forum_password_verify($password, \FORUM_DUMMY_PASSWORD_HASH, '');
	}

	public function needsRehash(string $hash): bool {
		return (bool) \forum_password_needs_rehash($hash);
	}

	public function resetKeyLifetime(): int {
		return (int) Markers::markup(\FORUM_PASSWORD_RESET_TTL);
	}
}
