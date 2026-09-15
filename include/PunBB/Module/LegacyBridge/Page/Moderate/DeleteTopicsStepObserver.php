<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Moderate\Event\DeleteTopicsStep;

/**
 * Runs the point at each step of deleting topics, with them as $topics and
 * $multi, and once gone the forums synced as $forum_ids and their posts as
 * $post_ids. The confirmation selects the point its topics are checked at.
 */
final class DeleteTopicsStepObserver {
	public const POINTS = array(
		DeleteTopicsStep::CONFIRMED	=> 'mr_confirm_delete_topics_form_submitted',
		DeleteTopicsStep::DELETED	=> 'mr_confirm_delete_topics_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope, private readonly ModerationRows $rows) {}

	public function observe(DeleteTopicsStep $event): void {
		$GLOBALS['topics'] = $event->topicIds();
		$GLOBALS['multi'] = count($event->topicIds()) > 1;

		if ($event->step() === DeleteTopicsStep::CONFIRMED)
			$this->rows->select(ModeratedTopicsPlugin::CHECK_TOPICS, 'mr_confirm_delete_topics_qr_verify_topic_ids');
		else
		{
			$GLOBALS['forum_ids'] = $event->forumIds();
			$GLOBALS['post_ids'] = $event->postIds();
		}

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
