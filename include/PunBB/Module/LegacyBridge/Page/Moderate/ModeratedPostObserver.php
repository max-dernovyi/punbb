<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Moderate\Event\ModeratedPostAssembling;

/**
 * Runs the point at each stage of a moderated post with the post as
 * $cur_post, published once per post so what a point changes there stays for
 * the points after it, and the post's parts, subject, checkbox and count in
 * $forum_page, all read back.
 */
final class ModeratedPostObserver {
	public const POINTS = array(
		ModeratedPostAssembling::START				=> 'mr_post_actions_loop_start',
		ModeratedPostAssembling::IDENT				=> 'mr_row_pre_item_ident_merge',
		ModeratedPostAssembling::ROW				=> 'mr_post_actions_row_pre_display',
		ModeratedPostAssembling::PRE_ITEM_SELECT	=> 'mr_post_actions_pre_item_select',
		ModeratedPostAssembling::HEAD_OPTION		=> 'mr_post_actions_new_post_head_option',
		ModeratedPostAssembling::USER_IDENT			=> 'mr_post_actions_new_user_ident_data',
		ModeratedPostAssembling::ENTRY				=> 'mr_post_actions_new_post_entry_data',
	);

	/** $forum_page key => the group of parts it holds */
	private const GROUPS = array(
		'post_ident'	=> ModeratedPostAssembling::PART_POST_IDENT,
		'author_ident'	=> ModeratedPostAssembling::PART_AUTHOR_IDENT,
		'message'		=> ModeratedPostAssembling::PART_MESSAGE,
		'item_status'	=> ModeratedPostAssembling::PART_ITEM_STATUS,
	);

	/** The post last published as $cur_post. */
	private ?object $published = null;

	public function __construct(private readonly PageScope $scope, private readonly ModerationRows $rows) {}

	public function observe(ModeratedPostAssembling $event): void {
		$point = self::POINTS[$event->stage()];
		if (!LegacyScope::attached($point))
			return;

		if ($this->published !== $event->post())
		{
			$post = $this->rows->post($event->post());
			if ($event->stage() !== ModeratedPostAssembling::START)
				$post['username'] = $post['poster'] ?? '';

			$GLOBALS['cur_post'] = $post;
			$this->published = $event->post();
		}
		else if ($event->stage() !== ModeratedPostAssembling::START && is_array($GLOBALS['cur_post'] ?? null) && !array_key_exists('username', $GLOBALS['cur_post']))
			$GLOBALS['cur_post']['username'] = $GLOBALS['cur_post']['poster'] ?? '';

		$page = ForumPage::all();
		foreach (self::GROUPS as $key => $group)
			$page[$key] = PartGroups::group($event, $group);
		$page['item_subject'] = $event->subject();
		$page['item_count'] = $event->itemCount();
		$page['start_from'] = $event->number() - $event->itemCount() - ($event->stage() === ModeratedPostAssembling::START ? 1 : 0);

		if ($event->select() !== '')
			$page['item_select'] = $event->select();
		else
			unset($page['item_select']);

		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved($point, $event));

		$event->count((int) Markers::markup(ForumPage::get('item_count') ?? $event->itemCount()));

		foreach (self::GROUPS as $key => $group)
			PartGroups::replace($event, $group, ForumPage::get($key));

		$event->setSubject(Markers::markup(ForumPage::get('item_subject')));
		$event->setSelect(Markers::markup(ForumPage::get('item_select')));
	}
}
