<?php

declare(strict_types=1);

namespace PunBB\Module\Censoring\Cache;

/**
 * The list of censored words the rest of the board censors with. Posting,
 * registering and every page that shows a post read it and carry points of
 * their own, so it moves with them: the module declares it, the bootstrap's
 * side wires it.
 */
interface CensorCacheInterface {
	/** Rebuilds the list from the censored words stored now. */
	public function rebuild(): void;
}
