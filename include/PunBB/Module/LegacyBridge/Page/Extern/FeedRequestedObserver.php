<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extern;

use PunBB\Module\Extern\Event\FeedRequested;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs ex_set_syndication_type with the format as $type and the item count as
 * $show, both read back; a format extern.php does not write is ignored.
 */
final class FeedRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(FeedRequested $event): void {
		$GLOBALS['type'] = $event->type();
		$GLOBALS['show'] = $event->count();

		if (!LegacyScope::attached('ex_set_syndication_type'))
			return;

		$this->scope->observe('ex_set_syndication_type', $event);

		$type = Markers::markup($GLOBALS['type'] ?? '');
		if (in_array($type, FeedRequested::TYPES, true))
			$event->setType($type);

		$event->setCount((int) Markers::markup($GLOBALS['show'] ?? $event->count()));
	}
}
