<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Moderate\Event\ModerationFormRendering;

/**
 * Renders the points of the forms confirming a change of posts or topics at
 * their positions, with the posts as $posts or the topics as $topics, the
 * form's counts in $forum_page, read back for the fields that follow, its
 * hidden fields there at its start, read back, and the forum as $forum_id at its end.
 */
final class ModerationFormObserver {
	public const POINTS = array(
		ModerationFormRendering::DELETE_POSTS	=> array(
			ModerationFormRendering::OUTPUT_START			=> 'mr_confirm_delete_posts_output_start',
			ModerationFormRendering::PRE_FIELDSET			=> 'mr_confirm_delete_posts_pre_fieldset',
			ModerationFormRendering::PRE_CONFIRM_CHECKBOX	=> 'mr_confirm_delete_posts_pre_confirm_checkbox',
			ModerationFormRendering::PRE_FIELDSET_END		=> 'mr_confirm_delete_posts_pre_fieldset_end',
			ModerationFormRendering::FIELDSET_END			=> 'mr_confirm_delete_posts_fieldset_end',
			ModerationFormRendering::END					=> 'mr_confirm_delete_posts_end',
		),
		ModerationFormRendering::SPLIT_POSTS	=> array(
			ModerationFormRendering::OUTPUT_START			=> 'mr_confirm_split_posts_output_start',
			ModerationFormRendering::PRE_FIELDSET			=> 'mr_confirm_split_posts_pre_fieldset',
			ModerationFormRendering::PRE_SUBJECT			=> 'mr_confirm_split_posts_pre_subject',
			ModerationFormRendering::PRE_CONFIRM_CHECKBOX	=> 'mr_confirm_split_posts_pre_confirm_checkbox',
			ModerationFormRendering::PRE_FIELDSET_END		=> 'mr_confirm_split_posts_pre_fieldset_end',
			ModerationFormRendering::FIELDSET_END			=> 'mr_confirm_split_posts_fieldset_end',
			ModerationFormRendering::END					=> 'mr_confirm_split_posts_end',
		),
		ModerationFormRendering::MOVE_TOPICS	=> array(
			ModerationFormRendering::OUTPUT_START			=> 'mr_move_topics_output_start',
			ModerationFormRendering::PRE_FIELDSET			=> 'mr_move_topics_pre_fieldset',
			ModerationFormRendering::PRE_MOVE_TO_FORUM		=> 'mr_move_topics_pre_move_to_forum',
			ModerationFormRendering::PRE_REDIRECT_CHECKBOX	=> 'mr_move_topics_pre_redirect_checkbox',
			ModerationFormRendering::PRE_FIELDSET_END		=> 'mr_move_topics_pre_fieldset_end',
			ModerationFormRendering::FIELDSET_END			=> 'mr_move_topics_fieldset_end',
			ModerationFormRendering::END					=> 'mr_move_topics_end',
		),
		ModerationFormRendering::MERGE_TOPICS	=> array(
			ModerationFormRendering::OUTPUT_START			=> 'mr_merge_topics_output_start',
			ModerationFormRendering::PRE_FIELDSET			=> 'mr_merge_topics_pre_fieldset',
			ModerationFormRendering::PRE_REDIRECT_CHECKBOX	=> 'mr_merge_topics_pre_redirect_checkbox',
			ModerationFormRendering::PRE_FIELDSET_END		=> 'mr_merge_topics_pre_fieldset_end',
			ModerationFormRendering::FIELDSET_END			=> 'mr_merge_topics_fieldset_end',
			ModerationFormRendering::END					=> 'mr_merge_topics_end',
		),
		ModerationFormRendering::DELETE_TOPICS	=> array(
			ModerationFormRendering::OUTPUT_START			=> 'mr_delete_topics_output_start',
			ModerationFormRendering::PRE_FIELDSET			=> 'mr_delete_topics_pre_fieldset',
			ModerationFormRendering::PRE_CONFIRM_CHECKBOX	=> 'mr_delete_topics_pre_confirm_checkbox',
			ModerationFormRendering::PRE_FIELDSET_END		=> 'mr_delete_topics_pre_fieldset_end',
			ModerationFormRendering::FIELDSET_END			=> 'mr_delete_topics_fieldset_end',
			ModerationFormRendering::END					=> 'mr_delete_topics_end',
		),
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ModerationFormRendering $event): void {
		$point = self::POINTS[$event->form()][$event->position()];

		// The jump list below the page preselects the forum
		if ($event->position() === ModerationFormRendering::END)
			$GLOBALS['forum_id'] = $event->forumId();

		if (!LegacyScope::attached($point))
			return;

		$ids = $event->ids();
		if (in_array($event->form(), array(ModerationFormRendering::DELETE_POSTS, ModerationFormRendering::SPLIT_POSTS), true))
			$GLOBALS['posts'] = $ids;
		else if ($event->form() === ModerationFormRendering::MOVE_TOPICS)
		{
			$GLOBALS['action'] = count($ids) === 1 ? 'single' : 'multiple';
			$GLOBALS['topics'] = count($ids) === 1 ? $ids[0] : $ids;
		}
		else
			$GLOBALS['topics'] = $ids;

		$start = $event->position() === ModerationFormRendering::OUTPUT_START;
		if ($start)
		{
			$fields = array();
			foreach ($event->names() as $name)
				$fields[$name] = (string) $event->entry($name);

			ForumPage::set('hidden_fields', $fields);
		}

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));

		if ($start)
		{
			$returned = Markers::entries(ForumPage::get('hidden_fields'));

			foreach ($event->names() as $name)
				if (!array_key_exists($name, $returned))
					$event->remove($name);

			foreach ($returned as $name => $markup)
				$event->set((string) $name, $markup);
		}
	}
}
