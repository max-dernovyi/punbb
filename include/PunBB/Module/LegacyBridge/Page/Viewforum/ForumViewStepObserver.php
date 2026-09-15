<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewforum;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Viewforum\Event\ForumViewStep;

/**
 * Runs the point at each step of showing a forum. Once the page is worked out,
 * the moderators are left in $mods_array, whether the visitor moderates in
 * $forum_page['is_admmod'], whether they may post in $forum_user['may_post'],
 * and the page in $forum_page, as viewforum.php left them.
 */
final class ForumViewStepObserver {
	public const POINTS = array(
		ForumViewStep::SELECTED		=> 'vf_modify_forum_info',
		ForumViewStep::REDIRECTING	=> 'vf_redirect_forum_pre_redirect',
		ForumViewStep::PAGINATED	=> 'vf_modify_page_details',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ForumViewStep $event): void {
		if ($event->step() === ForumViewStep::PAGINATED)
		{
			$mods_array = array();
			foreach ($event->forum()->moderators() as $moderator)
				$mods_array[$moderator->username()] = $moderator->userId();

			$GLOBALS['mods_array'] = $mods_array;

			$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
			$page['is_admmod'] = $event->moderating();
			$page['num_pages'] = $event->pageCount();
			$page['page'] = $event->page();
			$GLOBALS['forum_page'] = $page;

			if (isset($GLOBALS['forum_user']) && is_array($GLOBALS['forum_user']))
				$GLOBALS['forum_user']['may_post'] = $event->mayPost();
		}

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
