<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Index;

use PunBB\Module\Index\Event\CategoryHeadAssembling;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs in_forum_pre_cat_head with the summary's labels as $forum_page['item_header'], read back.
 */
final class CategoryHeadObserver {
	public function __construct(private readonly PageScope $scope, private readonly ForumRows $forums) {}

	public function observe(CategoryHeadAssembling $event): void {
		if (!LegacyScope::attached('in_forum_pre_cat_head'))
			return;

		$this->forums->publishForum($event->firstForum());

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page['cat_count'] = $event->number();
		$page['item_header'] = array('subject' => ForumRows::group($event, CategoryHeadAssembling::SUBJECT), 'info' => ForumRows::group($event, CategoryHeadAssembling::INFO));
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved('in_forum_pre_cat_head', $event));

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$header = is_array($page['item_header'] ?? null) ? $page['item_header'] : array();
		ForumRows::replace($event, CategoryHeadAssembling::SUBJECT, $header['subject'] ?? null);
		ForumRows::replace($event, CategoryHeadAssembling::INFO, $header['info'] ?? null);
	}
}
