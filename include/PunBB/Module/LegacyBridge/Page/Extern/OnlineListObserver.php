<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extern;

use PunBB\Module\Extern\Event\OnlineListAssembling;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs ex_pre_online_output with the members as $users and the counts as
 * $num_guests and $num_users, all read back.
 */
final class OnlineListObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(OnlineListAssembling $event): void {
		$users = array();
		foreach ($event->names() as $name)
			$users[] = (string) $event->entry($name);

		$GLOBALS['users'] = $users;
		$GLOBALS['num_guests'] = $event->guests();
		$GLOBALS['num_users'] = $event->memberCount();

		if (!LegacyScope::attached('ex_pre_online_output'))
			return;

		$this->scope->observe('ex_pre_online_output', $event);

		foreach ($event->names() as $name)
			$event->remove($name);

		foreach (Markers::entries($GLOBALS['users'] ?? null) as $name => $markup)
			$event->set((string) $name, $markup);

		$event->setCounts((int) Markers::markup($GLOBALS['num_guests'] ?? 0), (int) Markers::markup($GLOBALS['num_users'] ?? 0));
	}
}
