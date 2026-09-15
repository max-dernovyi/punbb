<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Post;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Post\Event\ReviewPostAssembling;

/**
 * Runs the point at each stage of a post in the topic review with the post as
 * $cur_post, with any column a query point added, published once a post, and the review's counts in
 * $forum_page, the post's count read back. Before the post is placed its
 * heading is in $forum_page['post_ident'] and its message in ['message'],
 * both read back.
 */
final class ReviewPostObserver {
	public const POINTS = array(
		ReviewPostAssembling::ROW	=> 'po_topic_review_row_pre_display',
		ReviewPostAssembling::HEAD	=> 'po_topic_review_new_post_head_option',
		ReviewPostAssembling::ENTRY	=> 'po_topic_review_new_post_entry_data',
	);

	/** The post last published as $cur_post. */
	private ?object $published = null;

	public function __construct(private readonly PageScope $scope, private readonly PostingRows $rows) {}

	public function observe(ReviewPostAssembling $event): void {
		$point = self::POINTS[$event->stage()];
		if (!LegacyScope::attached($point))
			return;

		$row = $event->stage() === ReviewPostAssembling::ROW;

		// Published once per post, so what a point changes there stays for the points after it
		if ($this->published !== $event->post())
		{
			$GLOBALS['cur_post'] = $this->rows->post($event->post());
			$this->published = $event->post();
		}

		ForumPage::set('item_count', $event->itemCount());
		ForumPage::set('item_total', $event->itemTotal());

		if ($row)
		{
			$ident = array();
			foreach ($event->names() as $name)
				$ident[$name] = (string) $event->entry($name);

			ForumPage::set('post_ident', $ident);
			ForumPage::set('message', $event->message());
		}

		$event->append($this->scope->renderObserved($point, $event));

		$event->count((int) Markers::markup(ForumPage::get('item_count') ?? $event->itemCount()));

		if (!$row)
			return;

		$ident = Markers::entries(ForumPage::get('post_ident'));
		foreach ($event->names() as $name)
			if (!isset($ident[$name]))
				$event->remove($name);

		foreach ($ident as $name => $markup)
			$event->set((string) $name, $markup);

		$event->setMessage(Markers::markup(ForumPage::get('message')));
	}
}
