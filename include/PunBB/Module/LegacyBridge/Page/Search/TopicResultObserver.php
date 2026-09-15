<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Search\Event\TopicResultAssembling;

/**
 * Runs the point at each stage of a topic found with the topic as $cur_set,
 * its subject censored, and the row's parts in $forum_page, which are read
 * back, as is the count.
 */
final class TopicResultObserver {
	public const POINTS = array(
		TopicResultAssembling::TITLE_STATUS	=> 'se_results_topics_row_pre_item_subject_status_merge',
		TopicResultAssembling::TITLE		=> 'se_results_topics_row_pre_item_title_merge',
		TopicResultAssembling::NAV			=> 'se_results_topics_row_pre_item_nav_merge',
		TopicResultAssembling::SUBJECT		=> 'se_results_topics_row_pre_item_subject_merge',
		TopicResultAssembling::STATUS		=> 'se_results_topics_pre_item_status_merge',
		TopicResultAssembling::ROW			=> 'se_results_topics_row_pre_display',
	);

	/** $forum_page's item array => the parts of the row it holds */
	private const ITEMS = array(
		'item_status'		=> TopicResultAssembling::PART_STATUS,
		'item_title'		=> TopicResultAssembling::PART_TITLE,
		'item_title_status'	=> TopicResultAssembling::PART_TITLE_STATUS,
		'item_nav'			=> TopicResultAssembling::PART_NAV,
		'item_subject'		=> TopicResultAssembling::PART_SUBJECT,
	);

	public function __construct(private readonly PageScope $scope, private readonly SearchRows $rows) {}

	public function observe(TopicResultAssembling $event): void {
		$point = self::POINTS[$event->stage()];
		if (!LegacyScope::attached($point))
			return;

		$topic = $this->rows->row($event->topic());
		$topic['subject'] = $event->subject();
		$GLOBALS['cur_set'] = $topic;

		$page = ForumPage::all();
		foreach (self::ITEMS as $item => $group)
			$page[$item] = SearchParts::group($event, $group);
		$page['item_body'] = array('subject' => SearchParts::group($event, TopicResultAssembling::PART_BODY_SUBJECT), 'info' => SearchParts::group($event, TopicResultAssembling::PART_BODY_INFO));
		$page['item_style'] = $event->style();
		$page['item_count'] = $event->itemCount();
		$page['start_from'] = $event->number() - $event->itemCount();
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved($point, $event));

		$page = ForumPage::all();
		foreach (self::ITEMS as $item => $group)
			SearchParts::replace($event, $group, $page[$item] ?? null);

		$body = is_array($page['item_body'] ?? null) ? $page['item_body'] : array();
		SearchParts::replace($event, TopicResultAssembling::PART_BODY_SUBJECT, $body['subject'] ?? null);
		SearchParts::replace($event, TopicResultAssembling::PART_BODY_INFO, $body['info'] ?? null);

		$event->setStyle(Markers::markup($page['item_style'] ?? ''));
		$event->count((int) Markers::markup($page['item_count'] ?? $event->itemCount()));
	}
}
