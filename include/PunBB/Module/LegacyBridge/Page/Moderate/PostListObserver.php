<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Moderate\Event\PostListRendering;

/**
 * Renders the points of the moderation of a topic's posts outside a post, with
 * the options in $forum_page['main_head_options'] and ['main_foot_options']
 * and the buttons in ['mod_options'], all read back; at the end the forum as $forum_id.
 */
final class PostListObserver {
	public const POINTS = array(
		PostListRendering::OUTPUT_START		=> 'mr_post_actions_output_start',
		PostListRendering::PRE_MOD_OPTIONS	=> 'mr_post_actions_pre_mod_options',
		PostListRendering::END				=> 'mr_post_actions_end',
	);

	/** $forum_page key => the group of parts it holds */
	private const GROUPS = array(
		'main_head_options'	=> PostListRendering::HEAD_OPTIONS,
		'main_foot_options'	=> PostListRendering::FOOT_OPTIONS,
		'mod_options'		=> PostListRendering::MOD_OPTIONS,
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(PostListRendering $event): void {
		$point = self::POINTS[$event->position()];

		// The jump list below the page preselects the forum
		if ($event->position() === PostListRendering::END)
			$GLOBALS['forum_id'] = $event->forum()->id();

		if (!LegacyScope::attached($point))
			return;

		$page = ForumPage::all();
		foreach (self::GROUPS as $key => $group)
			$page[$key] = PartGroups::group($event, $group);
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved($point, $event));

		foreach (self::GROUPS as $key => $group)
			PartGroups::replace($event, $group, ForumPage::get($key));
	}
}
