<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Moderate\Event\PostsModerationStep;

/**
 * Runs the point at each step of moderating a topic's posts, with the topic
 * as $tid and $cur_topic, the posts as $posts, and the topic split off as
 * $new_tid and $new_subject. A confirmation selects the point its posts are
 * checked at.
 */
final class PostsModerationStepObserver {
	public const POINTS = array(
		PostsModerationStep::SELECTED			=> 'mr_post_actions_selected',
		PostsModerationStep::DELETE_SUBMITTED	=> 'mr_delete_posts_form_submitted',
		PostsModerationStep::DELETE_CONFIRMED	=> 'mr_confirm_delete_posts_form_submitted',
		PostsModerationStep::DELETED			=> 'mr_confirm_delete_posts_pre_redirect',
		PostsModerationStep::SPLIT_SUBMITTED	=> 'mr_split_posts_form_submitted',
		PostsModerationStep::SPLIT_CONFIRMED	=> 'mr_confirm_split_posts_form_submitted',
		PostsModerationStep::SPLIT				=> 'mr_confirm_split_posts_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope, private readonly ModerationRows $rows) {}

	public function observe(PostsModerationStep $event): void {
		$topic = $event->topic();
		if ($topic !== null)
		{
			$GLOBALS['tid'] = $topic->id();
			$GLOBALS['cur_topic'] = $this->rows->topic($topic);
		}

		if ($event->postIds() !== array())
			$GLOBALS['posts'] = $event->postIds();

		switch ($event->step())
		{
			case PostsModerationStep::DELETE_CONFIRMED:
				$this->rows->select(ModeratedPostsPlugin::CHECK_REPLIES, 'mr_confirm_delete_posts_qr_verify_post_ids');
				break;

			case PostsModerationStep::SPLIT_CONFIRMED:
				$this->rows->select(ModeratedPostsPlugin::CHECK_REPLIES, 'mr_confirm_split_posts_qr_verify_post_ids');
				break;

			case PostsModerationStep::SPLIT:
				$GLOBALS['new_tid'] = $event->newTopicId();
				$GLOBALS['new_subject'] = $event->newSubject();
				break;
		}

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
