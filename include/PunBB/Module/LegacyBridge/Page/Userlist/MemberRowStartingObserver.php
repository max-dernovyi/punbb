<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Userlist;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Userlist\Event\MemberRowStarting;

/**
 * Runs ul_results_row_pre_data with the member's row as $user_data.
 */
final class MemberRowStartingObserver {
	public function __construct(private readonly PageScope $scope, private readonly MemberRows $rows) {}

	public function observe(MemberRowStarting $event): void {
		$GLOBALS['user_data'] = $this->rows->row($event->member());

		$event->append($this->scope->renderObserved('ul_results_row_pre_data', $event));
	}
}
