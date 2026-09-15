<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Index;

use PunBB\Module\Index\Event\OnlineVisitorListing;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs in_users_online_add_online_user_loop with the visitor's row as $forum_user_online.
 */
final class OnlineVisitorObserver {
	public function __construct(private readonly PageScope $scope, private readonly KeptRows $rows) {}

	public function observe(OnlineVisitorListing $event): void {
		if (!LegacyScope::attached('in_users_online_add_online_user_loop'))
			return;

		$visitor = $event->visitor();
		$GLOBALS['forum_user_online'] = $this->rows->row($visitor) ?? array('user_id' => $visitor->userId(), 'ident' => $visitor->ident());

		$event->append($this->scope->renderObserved('in_users_online_add_online_user_loop', $event));
	}
}
