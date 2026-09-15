<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Moderation;

/**
 * The moderators each forum lists. A member whose group no longer moderates
 * stays listed until the lists are cleaned; the groups, users and profile
 * pages clean them when they take moderation away.
 */
interface ModeratorListsInterface {
	/** Takes every member whose group does not moderate off every forum's list. */
	public function clean(): void;
}
