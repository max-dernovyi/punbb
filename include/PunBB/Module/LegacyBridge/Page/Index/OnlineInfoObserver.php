<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Index;

use PunBB\Module\Index\Event\OnlineInfoAssembling;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs in_users_online_pre_online_info_output with the counts as
 * $forum_page['online_info'] and the members as $users, both read back.
 */
final class OnlineInfoObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(OnlineInfoAssembling $event): void {
		if (!LegacyScope::attached('in_users_online_pre_online_info_output'))
			return;

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page['num_guests'] = $event->guestCount();
		$page['num_users'] = $event->memberCount();
		$page['online_info'] = ForumRows::group($event, OnlineInfoAssembling::COUNTS);
		$GLOBALS['forum_page'] = $page;
		$GLOBALS['users'] = array_values(ForumRows::group($event, OnlineInfoAssembling::MEMBERS));

		$event->append($this->scope->renderObserved('in_users_online_pre_online_info_output', $event));

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		ForumRows::replace($event, OnlineInfoAssembling::COUNTS, $page['online_info'] ?? null);
		ForumRows::replace($event, OnlineInfoAssembling::MEMBERS, $GLOBALS['users'] ?? null);
	}
}
