<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewtopic;

use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Viewtopic\Event\TopicViewStep;

/**
 * Runs the point at each step of showing a topic. Once the page is worked out,
 * the moderators are left in $mods_array, whether the visitor moderates in
 * $forum_page['is_admmod'], whether they may post in $forum_user['may_post']
 * and the page in $forum_page, as viewtopic.php left them; after the point the
 * topic's subject in $cur_topic is the censored one the page shows.
 */
final class TopicViewStepObserver {
	public const POINTS = array(
		TopicViewStep::SELECTED		=> 'vt_modify_topic_info',
		TopicViewStep::PAGINATED	=> 'vt_modify_page_details',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(TopicViewStep $event): void {
		if ($event->step() === TopicViewStep::SELECTED)
		{
			$this->scope->observe(self::POINTS[TopicViewStep::SELECTED], $event);
			return;
		}

		$mods_array = array();
		foreach ($event->topic()->moderators() as $moderator)
			$mods_array[$moderator->username()] = $moderator->userId();

		$GLOBALS['mods_array'] = $mods_array;

		ForumPage::set('is_admmod', $event->moderating());
		ForumPage::set('num_pages', $event->pageCount());
		ForumPage::set('page', $event->page());
		ForumPage::set('start_from', $event->offset());
		ForumPage::set('finish_at', $event->last());

		if (isset($GLOBALS['forum_user']) && is_array($GLOBALS['forum_user']))
			$GLOBALS['forum_user']['may_post'] = $event->mayPost();

		$this->scope->observe(self::POINTS[TopicViewStep::PAGINATED], $event);

		if (isset($GLOBALS['cur_topic']) && is_array($GLOBALS['cur_topic']))
			$GLOBALS['cur_topic']['subject'] = $event->shownSubject();
	}
}
