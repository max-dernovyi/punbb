<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewforum;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Viewforum\Event\TopicListHeadAssembling;

/**
 * Renders vf_main_output_start with the list's labels in
 * $forum_page['item_header'] and its options in main_head_options and
 * main_foot_options, all read back.
 */
final class TopicListHeadObserver {
	/** $forum_page key => the group of parts it holds */
	private const OPTIONS = array(
		'main_head_options'	=> TopicListHeadAssembling::HEAD_OPTIONS,
		'main_foot_options'	=> TopicListHeadAssembling::FOOT_OPTIONS,
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(TopicListHeadAssembling $event): void {
		if (!LegacyScope::attached('vf_main_output_start'))
			return;

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page['item_header'] = array('subject' => self::group($event, TopicListHeadAssembling::SUBJECT), 'info' => self::group($event, TopicListHeadAssembling::INFO));
		foreach (self::OPTIONS as $key => $group)
			$page[$key] = self::group($event, $group);
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved('vf_main_output_start', $event));

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$header = is_array($page['item_header'] ?? null) ? $page['item_header'] : array();
		self::replace($event, TopicListHeadAssembling::SUBJECT, $header['subject'] ?? null);
		self::replace($event, TopicListHeadAssembling::INFO, $header['info'] ?? null);
		foreach (self::OPTIONS as $key => $group)
			self::replace($event, $group, $page[$key] ?? null);
	}

	/** @return array<string, string> */
	private static function group(TopicListHeadAssembling $event, string $group): array {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[$name] = (string) $event->entry($group, $name);

		return $parts;
	}

	private static function replace(TopicListHeadAssembling $event, string $group, mixed $value): void {
		foreach ($event->names($group) as $name)
			$event->remove($group, $name);

		foreach (Markers::entries($value) as $name => $markup)
			$event->set($group, (string) $name, $markup);
	}
}
