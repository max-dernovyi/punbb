<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Userlist;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Userlist\Event\MemberRowAssembling;
use PunBB\Module\Userlist\Event\MemberTableAssembling;

/**
 * The cells extension code left in $forum_page['table_header'] or ['table_row'], put back on the event.
 */
final class RowCells {
	public static function readBack(MemberTableAssembling|MemberRowAssembling $event, mixed $cells): void {
		$returned = Markers::entries($cells);

		foreach ($event->names() as $name)
			if (!array_key_exists($name, $returned))
				$event->remove($name);

		foreach ($returned as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
