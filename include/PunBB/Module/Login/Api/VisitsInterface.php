<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Api;

/**
 * The visits the online list shows.
 */
interface VisitsInterface {
	/** Takes the guests visiting from each of $addresses off the list. */
	public function endGuestVisit(string ...$addresses): void;

	/** Takes each of the members $userIds off the list. */
	public function endVisit(int ...$userIds): void;
}
