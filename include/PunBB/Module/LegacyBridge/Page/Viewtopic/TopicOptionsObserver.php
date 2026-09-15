<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewtopic;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Viewtopic\Event\TopicOptionsAssembling;

/**
 * Renders vt_main_output_start with the options above the posts in
 * $forum_page['main_head_options'] and, for a moderator of the topic, those
 * below in ['main_foot_options'], which the page script set for nobody else;
 * both read back.
 */
final class TopicOptionsObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(TopicOptionsAssembling $event): void {
		if (!LegacyScope::attached('vt_main_output_start'))
			return;

		ForumPage::set('main_head_options', self::group($event, TopicOptionsAssembling::HEAD_OPTIONS));

		$page = ForumPage::all();
		unset($page['main_foot_options']);
		if ($event->moderating() || $event->names(TopicOptionsAssembling::FOOT_OPTIONS) !== array())
			$page['main_foot_options'] = self::group($event, TopicOptionsAssembling::FOOT_OPTIONS);
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved('vt_main_output_start', $event));

		self::replace($event, TopicOptionsAssembling::HEAD_OPTIONS, ForumPage::get('main_head_options'));
		self::replace($event, TopicOptionsAssembling::FOOT_OPTIONS, ForumPage::get('main_foot_options'));
	}

	/** @return array<string, string> */
	private static function group(TopicOptionsAssembling $event, string $group): array {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[$name] = (string) $event->entry($group, $name);

		return $parts;
	}

	private static function replace(TopicOptionsAssembling $event, string $group, mixed $value): void {
		foreach ($event->names($group) as $name)
			$event->remove($group, $name);

		foreach (Markers::entries($value) as $name => $markup)
			$event->set($group, (string) $name, $markup);
	}
}
