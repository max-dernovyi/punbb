<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewtopic;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Viewtopic\Event\PostAssembling;

/**
 * Runs the point at each stage of a post with the post as $cur_post, published
 * once per post so what a point changes there stays for the points after it,
 * and the post's parts, subject and count in $forum_page, all read back. At
 * the cached stage the parts kept for the poster's next posts are in
 * $user_data_cache, and read back from there.
 */
final class PostAssemblingObserver {
	public const POINTS = array(
		PostAssembling::START		=> 'vt_post_loop_start',
		PostAssembling::IDENT		=> 'vt_row_pre_post_ident_merge',
		PostAssembling::CONTACTS	=> 'vt_row_pre_post_contacts_merge',
		PostAssembling::ACTIONS		=> 'vt_row_pre_post_actions_merge',
		PostAssembling::ROW			=> 'vt_row_pre_display',
		PostAssembling::CACHED		=> 'vt_row_add_user_data_cache',
		PostAssembling::ENTRY		=> 'vt_row_new_post_entry_data',
	);

	/** $forum_page key => the group of parts it holds */
	private const GROUPS = array(
		'post_ident'	=> PostAssembling::PART_POST_IDENT,
		'author_ident'	=> PostAssembling::PART_AUTHOR_IDENT,
		'author_info'	=> PostAssembling::PART_AUTHOR_INFO,
		'post_contacts'	=> PostAssembling::PART_POST_CONTACTS,
		'post_actions'	=> PostAssembling::PART_POST_ACTIONS,
		'post_options'	=> PostAssembling::PART_POST_OPTIONS,
		'message'		=> PostAssembling::PART_MESSAGE,
		'item_status'	=> PostAssembling::PART_ITEM_STATUS,
	);

	/** The groups kept for a poster's next posts. */
	private const CACHED = array('author_ident', 'author_info', 'post_contacts');

	/** The post last published as $cur_post. */
	private ?object $published = null;

	public function __construct(private readonly PageScope $scope, private readonly ViewedTopicRows $rows) {}

	public function observe(PostAssembling $event): void {
		$point = self::POINTS[$event->stage()];
		if (!LegacyScope::attached($point))
			return;

		if ($this->published !== $event->post())
		{
			$GLOBALS['cur_post'] = $this->rows->post($event->post());
			$this->published = $event->post();
		}

		$cached = $event->stage() === PostAssembling::CACHED;
		$posterId = $event->post()->posterId();

		if ($cached)
		{
			$cache = self::userDataCache();
			$kept = array();
			foreach (self::CACHED as $key)
				$kept[$key] = self::group($event, self::GROUPS[$key]);
			$cache[$posterId] = $kept;
			$GLOBALS['user_data_cache'] = $cache;
		}
		else
		{
			$page = ForumPage::all();
			foreach (self::GROUPS as $key => $group)
				$page[$key] = self::group($event, $group);
			$page['item_subject'] = $event->subject();
			$GLOBALS['forum_page'] = $page;
		}

		ForumPage::set('item_count', $event->itemCount());
		ForumPage::set('start_from', $event->number() - $event->itemCount() - ($event->stage() === PostAssembling::START ? 1 : 0));

		$event->append($this->scope->renderObserved($point, $event));

		$event->count((int) Markers::markup(ForumPage::get('item_count') ?? $event->itemCount()));

		if ($cached)
		{
			$cache = self::userDataCache();
			$kept = is_array($cache[$posterId] ?? null) ? $cache[$posterId] : array();
			foreach (self::CACHED as $key)
				self::replace($event, self::GROUPS[$key], $kept[$key] ?? null);

			return;
		}

		foreach (self::GROUPS as $key => $group)
			self::replace($event, $group, ForumPage::get($key));

		$event->setSubject(Markers::markup(ForumPage::get('item_subject')));
	}

	/** @return array<mixed> $user_data_cache as extension code left it */
	private static function userDataCache(): array {
		return is_array($GLOBALS['user_data_cache'] ?? null) ? $GLOBALS['user_data_cache'] : array();
	}

	/** @return array<string, string> */
	private static function group(PostAssembling $event, string $group): array {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[$name] = (string) $event->entry($group, $name);

		return $parts;
	}

	private static function replace(PostAssembling $event, string $group, mixed $value): void {
		foreach ($event->names($group) as $name)
			$event->remove($group, $name);

		foreach (Markers::entries($value) as $name => $markup)
			$event->set($group, (string) $name, $markup);
	}
}
