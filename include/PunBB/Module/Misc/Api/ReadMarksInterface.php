<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Api;

use PunBB\Module\Misc\Api\Data\LastVisitInterface;

/**
 * What marking the board or a forum read reads and writes.
 */
interface ReadMarksInterface {
	/** Moves each member's last visit, so what was posted before it counts as read. */
	public function markBoardRead(LastVisitInterface ...$visits): void;

	/** The name of forum $forumId, when group $groupId may read it; null otherwise. */
	public function forumName(int $forumId, int $groupId): ?string;
}
