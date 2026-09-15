<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Search\Event\ForumResultAssembling;
use PunBB\Module\Search\Event\PostResultAssembling;
use PunBB\Module\Search\Event\ResultsHeadAssembling;
use PunBB\Module\Search\Event\ResultsRendering;
use PunBB\Module\Search\Event\SearchFormRendering;
use PunBB\Module\Search\Event\TopicResultAssembling;
use PunBB\Module\Search\Event\TopicResultsHeadAssembling;

/**
 * An event's group of named markup as the $forum_page array search.php kept it
 * in, and back.
 */
final class SearchParts {
	/** @return array<string, string> the parts of $group, by name */
	public static function group(ResultsHeadAssembling|ResultsRendering|TopicResultsHeadAssembling|PostResultAssembling|TopicResultAssembling|ForumResultAssembling|SearchFormRendering $event, string $group): array {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[$name] = (string) $event->entry($group, $name);

		return $parts;
	}

	/** The parts of $group replaced by what extension code left in a variable, which may be anything. */
	public static function replace(ResultsHeadAssembling|ResultsRendering|TopicResultsHeadAssembling|PostResultAssembling|TopicResultAssembling|ForumResultAssembling|SearchFormRendering $event, string $group, mixed $value): void {
		foreach ($event->names($group) as $name)
			$event->remove($group, $name);

		foreach (Markers::entries($value) as $name => $markup)
			$event->set($group, (string) $name, $markup);
	}
}
