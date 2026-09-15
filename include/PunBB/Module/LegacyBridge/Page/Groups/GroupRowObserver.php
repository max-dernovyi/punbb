<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Groups;

use PunBB\Module\Groups\Event\GroupRowAssembling;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the points around a listed group with its row as $cur_group and the
 * list's counts in $forum_page, read back. Before the group its options are
 * in $forum_page['group_options'], read back too.
 */
final class GroupRowObserver {
	public const POINTS = array(
		GroupRowAssembling::PRE_OUTPUT	=> 'agr_edit_group_row_pre_output',
		GroupRowAssembling::POST_OUTPUT	=> 'agr_edit_group_row_post_output',
	);

	public function __construct(private readonly PageScope $scope, private readonly GroupsRows $rows) {}

	public function observe(GroupRowAssembling $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['cur_group'] = $this->rows->listed($event->group());

		$options = $event->position() === GroupRowAssembling::PRE_OUTPUT;
		if ($options)
		{
			$entries = array();
			foreach ($event->names() as $name)
				$entries[$name] = (string) $event->entry($name);

			ForumPage::set('group_options', $entries);
		}

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));

		if ($options)
		{
			$entries = Markers::entries(ForumPage::get('group_options'));
			foreach ($event->names() as $name)
				if (!isset($entries[$name]))
					$event->remove($name);

			foreach ($entries as $name => $markup)
				$event->set((string) $name, $markup);
		}
	}
}
