<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Misc;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Misc\Event\MarkingReadStep;

/**
 * Runs the point at each step of marking the board or a forum read, with the
 * forum in $fid and, once it is marked, its name in $forum_name.
 */
final class MarkingReadObserver {
	/** step => the point marking the board read, the point marking a forum read */
	public const POINTS = array(
		MarkingReadStep::SELECTED	=> array('mi_markread_selected', 'mi_markforumread_selected'),
		MarkingReadStep::MARKED		=> array('mi_markread_pre_redirect', 'mi_markforumread_pre_redirect'),
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(MarkingReadStep $event): void {
		$forumId = $event->forumId();

		if ($forumId !== null)
		{
			$GLOBALS['fid'] = $forumId;

			if ($event->step() === MarkingReadStep::MARKED)
				$GLOBALS['forum_name'] = $event->forumName();
		}

		$this->scope->observe(self::POINTS[$event->step()][$forumId === null ? 0 : 1], $event);
	}
}
