<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extern;

use PunBB\Module\Extern\Authentication\BasicAuthenticationInterface;

/**
 * authenticate_user() of include/functions.php, with the extension code
 * attached to it, which leaves the member it signed in in $forum_user.
 */
final class LegacyBasicAuthentication implements BasicAuthenticationInterface {
	public function authenticate(string $username, string $password): void {
		\authenticate_user($username, $password);
	}
}
