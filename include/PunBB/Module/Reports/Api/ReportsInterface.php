<?php

declare(strict_types=1);

namespace PunBB\Module\Reports\Api;

use PunBB\Module\Reports\Api\Data\ReportInterface;

/**
 * The posts members reported to the administrators and moderators.
 */
interface ReportsInterface {
	/** @return list<ReportInterface> the reports nobody marked read, newest first */
	public function unread(): array;

	/** @return list<ReportInterface> the $limit reports marked read last, the last first */
	public function recentlyRead(int $limit): array;

	/**
	 * Marks the unread reports among $reportIds read by user $userId at $now.
	 *
	 * @param list<int> $reportIds
	 */
	public function markRead(array $reportIds, int $userId, int $now): void;
}
