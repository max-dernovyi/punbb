<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Cache;

/**
 * The list of bans every request is checked against. The bans and the users
 * pages rebuild it once they store a ban.
 */
interface BanCacheInterface {
	/** Rebuilds the list from the bans stored now. */
	public function rebuild(): void;
}
