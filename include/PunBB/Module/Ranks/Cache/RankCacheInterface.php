<?php

declare(strict_types=1);

namespace PunBB\Module\Ranks\Cache;

/**
 * The list of ranks the title beside every poster is chosen from. Every page
 * that shows a poster reads it, with points of its own, so it moves with
 * them: the module declares it, the bootstrap's side wires it.
 */
interface RankCacheInterface {
	/** Rebuilds the list from the ranks stored now. */
	public function rebuild(): void;
}
