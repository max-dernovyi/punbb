<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Search\Event\ForumResultAssembling;

/**
 * Runs the point at each stage of a forum subscribed to with the forum as
 * $cur_set, the categories opened in $forum_page['cat_count'], and the row's
 * parts in $forum_page — at a new category the summary's labels in
 * item_header — which are read back, as is the count.
 */
final class ForumResultObserver {
	public const POINTS = array(
		ForumResultAssembling::CATEGORY_HEAD	=> 'se_results_forums_row_pre_cat_head',
		ForumResultAssembling::REDIRECT_SUBJECT	=> 'se_results_forums_row_redirect_pre_item_subject_merge',
		ForumResultAssembling::REDIRECT_BODY	=> 'se_results_forums_row_redirect_pre_display',
		ForumResultAssembling::TITLE			=> 'se_results_forums_row_redirect_pre_item_title_merge',
		ForumResultAssembling::SUBJECT			=> 'se_results_forums_row_normal_pre_item_subject_merge',
		ForumResultAssembling::BODY				=> 'se_results_forums_row_normal_pre_display',
		ForumResultAssembling::ROW				=> 'se_results_forums_row_pre_display',
	);

	/** $forum_page's item array => the parts of the row it holds */
	private const ITEMS = array(
		'item_status'	=> ForumResultAssembling::PART_STATUS,
		'item_title'	=> ForumResultAssembling::PART_TITLE,
		'item_subject'	=> ForumResultAssembling::PART_SUBJECT,
	);

	public function __construct(private readonly PageScope $scope, private readonly SearchRows $rows) {}

	public function observe(ForumResultAssembling $event): void {
		$point = self::POINTS[$event->stage()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['cur_set'] = $this->rows->row($event->forum());

		$page = ForumPage::all();
		foreach (self::ITEMS as $item => $group)
			$page[$item] = SearchParts::group($event, $group);
		$page['item_body'] = array('subject' => SearchParts::group($event, ForumResultAssembling::PART_BODY_SUBJECT), 'info' => SearchParts::group($event, ForumResultAssembling::PART_BODY_INFO));
		$page['item_header'] = array('subject' => SearchParts::group($event, ForumResultAssembling::PART_HEADER_SUBJECT), 'info' => SearchParts::group($event, ForumResultAssembling::PART_HEADER_INFO));
		$page['item_style'] = $event->style();
		$page['item_count'] = $event->itemCount();
		$page['cat_count'] = $event->categoryCount();
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved($point, $event));

		$page = ForumPage::all();
		foreach (self::ITEMS as $item => $group)
			SearchParts::replace($event, $group, $page[$item] ?? null);

		$body = is_array($page['item_body'] ?? null) ? $page['item_body'] : array();
		SearchParts::replace($event, ForumResultAssembling::PART_BODY_SUBJECT, $body['subject'] ?? null);
		SearchParts::replace($event, ForumResultAssembling::PART_BODY_INFO, $body['info'] ?? null);

		$header = is_array($page['item_header'] ?? null) ? $page['item_header'] : array();
		SearchParts::replace($event, ForumResultAssembling::PART_HEADER_SUBJECT, $header['subject'] ?? null);
		SearchParts::replace($event, ForumResultAssembling::PART_HEADER_INFO, $header['info'] ?? null);

		$event->setStyle(Markers::markup($page['item_style'] ?? ''));
		$event->count((int) Markers::markup($page['item_count'] ?? $event->itemCount()));
	}
}
