<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Moderate\Event\TopicListRendering;

/**
 * Renders the points of the moderation of a forum's topics outside a row, with
 * the labels in $forum_page['item_header'], the options in main_head_options
 * and main_foot_options and the buttons in mod_options, all read back; at the
 * end the forum as $forum_id.
 */
final class TopicListObserver {
	public const POINTS = array(
		TopicListRendering::OUTPUT_START	=> 'mr_topic_actions_output_start',
		TopicListRendering::POST_TOPIC_LIST	=> 'mr_topic_actions_post_topic_list',
		TopicListRendering::PRE_MOD_OPTIONS	=> 'mr_topic_actions_pre_mod_option_output',
		TopicListRendering::END				=> 'mr_end',
	);

	/** $forum_page key => the group of parts it holds */
	private const GROUPS = array(
		'main_head_options'	=> TopicListRendering::HEAD_OPTIONS,
		'main_foot_options'	=> TopicListRendering::FOOT_OPTIONS,
		'mod_options'		=> TopicListRendering::MOD_OPTIONS,
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(TopicListRendering $event): void {
		$point = self::POINTS[$event->position()];

		// The jump list below the page preselects the forum
		if ($event->position() === TopicListRendering::END)
			$GLOBALS['forum_id'] = $event->forum()->id();

		if (!LegacyScope::attached($point))
			return;

		$page = ForumPage::all();
		$page['item_header'] = array('subject' => PartGroups::group($event, TopicListRendering::SUBJECT), 'info' => PartGroups::group($event, TopicListRendering::INFO));
		foreach (self::GROUPS as $key => $group)
			$page[$key] = PartGroups::group($event, $group);
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved($point, $event));

		$header = ForumPage::get('item_header');
		PartGroups::replace($event, TopicListRendering::SUBJECT, is_array($header) ? ($header['subject'] ?? null) : null);
		PartGroups::replace($event, TopicListRendering::INFO, is_array($header) ? ($header['info'] ?? null) : null);

		foreach (self::GROUPS as $key => $group)
			PartGroups::replace($event, $group, ForumPage::get($key));
	}
}
