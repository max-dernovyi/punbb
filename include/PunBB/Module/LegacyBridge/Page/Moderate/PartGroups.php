<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Moderate\Event\ModeratedPostAssembling;
use PunBB\Module\Moderate\Event\ModeratedTopicAssembling;
use PunBB\Module\Moderate\Event\PostListRendering;
use PunBB\Module\Moderate\Event\TopicListRendering;

/**
 * An event's groups of parts as moderate.php kept them in $forum_page's arrays.
 */
final class PartGroups {
	/** @return array<string, string> the parts of $group, by name */
	public static function group(ModeratedPostAssembling|ModeratedTopicAssembling|PostListRendering|TopicListRendering $event, string $group): array {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[$name] = (string) $event->entry($group, $name);

		return $parts;
	}

	/** The parts of $group replaced by what extension code left in a variable, which may be anything. */
	public static function replace(ModeratedPostAssembling|ModeratedTopicAssembling|PostListRendering|TopicListRendering $event, string $group, mixed $value): void {
		foreach ($event->names($group) as $name)
			$event->remove($group, $name);

		foreach (Markers::entries($value) as $name => $markup)
			$event->set($group, (string) $name, $markup);
	}
}
