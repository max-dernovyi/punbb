<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Security;

/**
 * Secret material from the CSPRNG: salts, mailed keys, passwords generated for a member.
 */
interface RandomKeysInterface {
	/**
	 * A key of $length characters: printable ASCII, letters and digits when
	 * $readable, lower-case hex when $hash.
	 */
	public function key(int $length, bool $readable = false, bool $hash = false): string;
}
