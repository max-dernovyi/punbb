<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewforum;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Viewforum\Event\TopicRowAssembling;

/**
 * Runs the point at each stage of a topic's row with the topic as $cur_topic,
 * its subject censored after the first stage, and the row's parts in
 * $forum_page, which are read back, as is the row count.
 */
final class TopicRowObserver {
	public const POINTS = array(
		TopicRowAssembling::START			=> 'vf_topic_loop_start',
		TopicRowAssembling::MOVED_SUBJECT	=> 'vf_topic_loop_moved_topic_pre_item_subject_merge',
		TopicRowAssembling::TITLE_STATUS	=> 'vf_topic_loop_normal_topic_pre_item_title_status_merge',
		TopicRowAssembling::TITLE			=> 'vf_topic_loop_normal_topic_pre_item_title_merge',
		TopicRowAssembling::NAV				=> 'vf_topic_loop_normal_topic_pre_item_nav_merge',
		TopicRowAssembling::SUBJECT			=> 'vf_row_pre_item_subject_merge',
		TopicRowAssembling::STATUS			=> 'vf_row_pre_item_status_merge',
		TopicRowAssembling::ROW				=> 'vf_row_pre_display',
	);

	public function __construct(private readonly PageScope $scope, private readonly TopicRows $rows) {}

	public function observe(TopicRowAssembling $event): void {
		$point = self::POINTS[$event->stage()];
		if (!LegacyScope::attached($point))
			return;

		$topic = $this->rows->topic($event->topic());
		$topic['subject'] = $event->subject();
		$GLOBALS['cur_topic'] = $topic;

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page = TopicRows::page($page, $event);
		$page['item_count'] = $event->itemCount();
		$page['start_from'] = $event->number() - $event->itemCount() - ($event->stage() === TopicRowAssembling::START ? 1 : 0);
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved($point, $event));

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		TopicRows::readBack($page, $event);
		$event->count((int) Markers::markup($page['item_count'] ?? $event->itemCount()));
	}
}
