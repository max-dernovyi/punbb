<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Forums;

use PunBB\Module\Forums\Event\PermissionsComparing;
use PunBB\Module\Forums\Model\ForumPermissions;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs afo_save_forum_pre_perms_compare with the group as $cur_group and its
 * permissions as $perms_default, $perms_old and $perms_new, and reads the
 * three back as the page script compared them: each value as an integer,
 * which the statements after the comparison see.
 */
final class PermissionsComparingObserver {
	private const PERMISSIONS = array('perms_default', 'perms_old', 'perms_new');

	public function __construct(private readonly PageScope $scope, private readonly ForumsRows $rows) {}

	public function observe(PermissionsComparing $event): void {
		$GLOBALS['cur_group'] = $this->rows->group($event->group());
		$GLOBALS['perms_default'] = ForumsRows::values($event->defaults());
		$GLOBALS['perms_old'] = ForumsRows::values($event->shown());
		$GLOBALS['perms_new'] = ForumsRows::values($event->submitted());

		$this->scope->observe('afo_save_forum_pre_perms_compare', $event);

		$compared = array();
		foreach (self::PERMISSIONS as $name)
		{
			$values = array_map('intval', Markers::entries($GLOBALS[$name] ?? array()));
			$GLOBALS[$name] = $values;
			$compared[] = new ForumPermissions($event->group()->groupId(), ($values['read_forum'] ?? 0) !== 0, ($values['post_replies'] ?? 0) !== 0, ($values['post_topics'] ?? 0) !== 0);
		}

		$event->change(...$compared);
	}
}
