<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Moderate\Event\MergeTopicsStep;

/**
 * Runs the point at each step of merging topics, with them as $topics and, once merged, the topic they went into as $merge_to_tid.
 */
final class MergeTopicsStepObserver {
	public const POINTS = array(
		MergeTopicsStep::CONFIRMED	=> 'mr_confirm_merge_topics_form_submitted',
		MergeTopicsStep::MERGED		=> 'mr_confirm_merge_topics_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(MergeTopicsStep $event): void {
		$GLOBALS['topics'] = $event->topicIds();

		if ($event->step() === MergeTopicsStep::MERGED)
			$GLOBALS['merge_to_tid'] = $event->mergedInto();

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
