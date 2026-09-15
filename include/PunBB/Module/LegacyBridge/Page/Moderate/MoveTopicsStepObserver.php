<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Moderate\Event\MoveTopicsStep;

/**
 * Runs the point at each step of moving topics, once moved with the topics as
 * $topics and the forum as $move_to_forum and $move_to_forum_name. The
 * confirmation selects the point its topics are checked at.
 */
final class MoveTopicsStepObserver {
	public const POINTS = array(
		MoveTopicsStep::CONFIRMED	=> 'mr_confirm_move_topics_form_submitted',
		MoveTopicsStep::MOVED		=> 'mr_confirm_move_topics_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope, private readonly ModerationRows $rows) {}

	public function observe(MoveTopicsStep $event): void {
		if ($event->step() === MoveTopicsStep::CONFIRMED)
			$this->rows->select(ModeratedTopicsPlugin::CHECK_TOPICS, 'mr_confirm_move_topics_qr_verify_topic_ids');
		else
		{
			$GLOBALS['topics'] = $event->topicIds();
			$GLOBALS['move_to_forum'] = $event->forumId();
			$GLOBALS['move_to_forum_name'] = $event->forumName();
		}

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
