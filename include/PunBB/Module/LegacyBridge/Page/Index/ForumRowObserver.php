<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Index;

use PunBB\Module\Index\Event\ForumRowAssembling;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs the point at each stage of a forum's row with the forum as $cur_forum
 * and the row's parts in $forum_page, which are read back, as is the row count.
 */
final class ForumRowObserver {
	public const POINTS = array(
		ForumRowAssembling::START				=> 'in_forum_loop_start',
		ForumRowAssembling::REDIRECT_SUBJECT	=> 'in_redirect_row_pre_item_subject_merge',
		ForumRowAssembling::REDIRECT_BODY		=> 'in_redirect_row_pre_display',
		ForumRowAssembling::TITLE				=> 'in_normal_row_pre_item_title_merge',
		ForumRowAssembling::MODERATORS			=> 'in_row_modify_modlist',
		ForumRowAssembling::SUBJECT				=> 'in_normal_row_pre_item_subject_merge',
		ForumRowAssembling::BODY				=> 'in_normal_row_pre_display',
		ForumRowAssembling::ROW					=> 'in_row_pre_display',
	);

	public function __construct(private readonly PageScope $scope, private readonly ForumRows $forums) {}

	public function observe(ForumRowAssembling $event): void {
		$point = self::POINTS[$event->stage()];
		if (!LegacyScope::attached($point))
			return;

		$this->forums->publishForum($event->forum());

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page = ForumRows::page($page, $event);
		$page['item_count'] = $event->itemCount();

		if ($event->stage() === ForumRowAssembling::MODERATORS)
		{
			$page['mods_array'] = array();
			foreach ($event->forum()->moderators() as $moderator)
				$page['mods_array'][$moderator->username()] = $moderator->userId();
		}

		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved($point, $event));

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		ForumRows::readBack($page, $event);
		$event->count((int) Markers::markup($page['item_count'] ?? $event->itemCount()));
	}
}
