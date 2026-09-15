<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Moderate\Event\TopicStateStep;

/**
 * Runs the point at each step of opening, closing, sticking or unsticking
 * topics, with whether they close as $action, those of the list as $topics,
 * a link's topic as $topic_id, $stick or $unstick and its subject as $subject.
 * The step selected picks the points the topic's subject is read and the
 * topics are changed at.
 */
final class TopicStateStepObserver {
	public const POINTS = array(
		TopicStateStep::OPEN_CLOSE_SELECTED	=> 'mr_open_close_topic_selected',
		TopicStateStep::LIST_CHANGED		=> 'mr_open_close_multi_topics_pre_redirect',
		TopicStateStep::LINK_CHANGED		=> 'mr_open_close_single_topic_pre_redirect',
		TopicStateStep::STICK_SELECTED		=> 'mr_stick_topic_selected',
		TopicStateStep::STUCK				=> 'mr_stick_topic_pre_redirect',
		TopicStateStep::UNSTICK_SELECTED	=> 'mr_unstick_topic_selected',
		TopicStateStep::UNSTUCK				=> 'mr_unstick_topic_pre_redirect',
	);

	/** step => the variable the page script held a link's topic in */
	private const TOPIC_VARIABLES = array(
		TopicStateStep::LINK_CHANGED		=> 'topic_id',
		TopicStateStep::STICK_SELECTED		=> 'stick',
		TopicStateStep::STUCK				=> 'stick',
		TopicStateStep::UNSTICK_SELECTED	=> 'unstick',
		TopicStateStep::UNSTUCK				=> 'unstick',
	);

	public function __construct(private readonly PageScope $scope, private readonly ModerationRows $rows) {}

	public function observe(TopicStateStep $event): void {
		$step = $event->step();

		switch ($step)
		{
			case TopicStateStep::OPEN_CLOSE_SELECTED:
				$GLOBALS['action'] = $event->closing() ? 1 : 0;
				$this->rows->select(ModeratedTopicsPlugin::CHECK_SUBJECT, 'mr_open_close_single_topic_qr_get_subject');
				$this->rows->select(ModeratedTopicsPlugin::CHECK_CLOSE, $event->listed() ? 'mr_open_close_multi_topics_qr_open_close_topics' : 'mr_open_close_single_topic_qr_open_close_topic');
				break;

			case TopicStateStep::STICK_SELECTED:
				$this->rows->select(ModeratedTopicsPlugin::CHECK_SUBJECT, 'mr_stick_topic_qr_get_subject');
				break;

			case TopicStateStep::UNSTICK_SELECTED:
				$this->rows->select(ModeratedTopicsPlugin::CHECK_SUBJECT, 'mr_unstick_topic_qr_get_subject');
				break;

			case TopicStateStep::LIST_CHANGED:
				$GLOBALS['topics'] = $event->topicIds();
				break;
		}

		if (isset(self::TOPIC_VARIABLES[$step]))
			$GLOBALS[self::TOPIC_VARIABLES[$step]] = $event->topicIds()[0] ?? 0;

		if ($event->subject() !== '')
			$GLOBALS['subject'] = $event->subject();

		$this->scope->observe(self::POINTS[$step], $event);
	}
}
