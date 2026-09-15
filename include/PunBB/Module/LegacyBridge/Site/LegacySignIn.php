<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Site\Security\SignInInterface;

/**
 * The login cookie config.php names in $cookie_name, sent through
 * forum_setcookie() with the authenticator forum_cookie_hash() builds, and
 * forum_session_regenerate() of include/functions.php.
 */
final class LegacySignIn implements SignInInterface {
	/** How long the guest's cookie that replaces a member's lives: two weeks. */
	private const SIGNED_OUT_FOR = 1209600;

	public function regenerateSession(): void {
		\forum_session_regenerate();
	}

	public function signIn(int $userId, string $passwordHash, string $salt, int $expire): void {
		\forum_setcookie(self::name(), base64_encode($userId.'|'.$passwordHash.'|'.$expire.'|'.Markers::markup(\forum_cookie_hash($userId, $passwordHash, $expire, $salt))), $expire);
	}

	public function expiryOf(array $cookies): int {
		$cookie = $cookies[self::name()] ?? null;
		$parts = explode('|', base64_decode(is_string($cookie) ? $cookie : ''));

		return isset($parts[2]) ? intval($parts[2]) : 0;
	}

	public function signOut(): void {
		$expire = time() + self::SIGNED_OUT_FOR;

		\forum_setcookie(self::name(), base64_encode('1|'.Markers::markup(\random_key(8, false, true)).'|'.$expire.'|'.Markers::markup(\random_key(8, false, true))), $expire);
	}

	private static function name(): string {
		return Markers::markup($GLOBALS['cookie_name'] ?? '');
	}
}
