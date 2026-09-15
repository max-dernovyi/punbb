<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Search\Event\SearchActionValidating;

/**
 * Runs sf_fn_validate_actions_start with the action in $action and the known
 * actions in $valid_actions, read back; a value the code returns decides.
 */
final class SearchActionObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(SearchActionValidating $event): void {
		$action = $event->action();
		$valid_actions = $event->actions();

		$returned = $this->scope->observe('sf_fn_validate_actions_start', $event, array('action' => &$action, 'valid_actions' => &$valid_actions));

		$event->setActions(array_values(Markers::entries($valid_actions)));

		if ($returned !== null)
			$event->decide((bool) $returned);
	}
}
