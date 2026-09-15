<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewforum;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Viewforum\Api\Data\ListedTopicInterface;
use PunBB\Module\Viewforum\Event\TopicsListing;

/**
 * Renders vf_pre_topic_loop_start with the page's topics as $topics; the
 * topics extension code left there are the ones listed.
 */
final class TopicsListingObserver {
	public function __construct(private readonly PageScope $scope, private readonly TopicRows $rows) {}

	public function observe(TopicsListing $event): void {
		if (!LegacyScope::attached('vf_pre_topic_loop_start'))
			return;

		$GLOBALS['topics'] = array_map(fn (ListedTopicInterface $topic): array => $this->rows->topic($topic), $event->topics());

		$event->append($this->scope->renderObserved('vf_pre_topic_loop_start', $event));

		$kept = array();
		foreach (is_array($GLOBALS['topics'] ?? null) ? $GLOBALS['topics'] : array() as $row)
			if (is_array($row) && isset($row['id']))
				$kept[] = (int) Markers::markup($row['id']);

		$event->keep($kept);
	}
}
