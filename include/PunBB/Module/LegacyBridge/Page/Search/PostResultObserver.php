<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Search\Event\PostResultAssembling;

/**
 * Renders the point at each stage of a post found with the post as $cur_set,
 * its subject censored, and what is built of it in $forum_page — the heading's
 * parts, the title, the author, the links, the text and the classes — all read
 * back, as is the count.
 */
final class PostResultObserver {
	public const POINTS = array(
		PostResultAssembling::IDENT	=> 'se_results_posts_row_pre_item_ident_merge',
		PostResultAssembling::ROW	=> 'se_results_posts_row_pre_display',
		PostResultAssembling::ENTRY	=> 'se_results_posts_row_new_post_entry_data',
	);

	/** $forum_page key => the group of parts it holds */
	private const GROUPS = array(
		'post_ident'	=> PostResultAssembling::PART_IDENT,
		'item_status'	=> PostResultAssembling::PART_STATUS,
		'post_actions'	=> PostResultAssembling::PART_ACTIONS,
	);

	public function __construct(private readonly PageScope $scope, private readonly SearchRows $rows) {}

	public function observe(PostResultAssembling $event): void {
		$point = self::POINTS[$event->stage()];
		if (!LegacyScope::attached($point))
			return;

		$post = $this->rows->row($event->post());
		$post['subject'] = $event->subject();
		$GLOBALS['cur_set'] = $post;

		$page = ForumPage::all();
		foreach (self::GROUPS as $key => $group)
			$page[$key] = SearchParts::group($event, $group);
		$page['item_subject'] = $event->title();
		$page['user_ident'] = $event->author();
		$page['message'] = $event->message();
		$page['item_count'] = $event->itemCount();
		$page['start_from'] = $event->number() - $event->itemCount();
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved($point, $event));

		$page = ForumPage::all();
		foreach (self::GROUPS as $key => $group)
			SearchParts::replace($event, $group, $page[$key] ?? null);
		$event->setTitle(Markers::markup($page['item_subject'] ?? ''));
		$event->setAuthor(Markers::markup($page['user_ident'] ?? ''));
		$event->setMessage(Markers::markup($page['message'] ?? ''));
		$event->count((int) Markers::markup($page['item_count'] ?? $event->itemCount()));
	}
}
