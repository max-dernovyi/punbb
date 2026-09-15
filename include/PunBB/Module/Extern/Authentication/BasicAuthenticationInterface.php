<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Authentication;

/**
 * Signs a feed reader in with the credentials of HTTP Basic authentication, for
 * the request alone. The login page shares the account lookup and the password
 * check, which carry points of their own, so it moves with login.php: the
 * module declares it, the bootstrap's side wires it.
 */
interface BasicAuthenticationInterface {
	/** The visitor becomes the member $username names when $password is theirs, and stays a guest otherwise. */
	public function authenticate(string $username, string $password): void;
}
