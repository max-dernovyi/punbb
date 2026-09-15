<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Event\ExtensionListRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the markup points of the lists of extensions and hotfixes at their
 * positions. Before what is available, its boxes are in $forum_page['ext_item'],
 * the failed directories' in $forum_page['ext_error'], and their counts in
 * $num_exts and $num_failed, all read back.
 */
final class ExtensionListObserver {
	/** list => position => point */
	public const POINTS = array(
		ExtensionListRendering::MANAGE		=> array(
			ExtensionListRendering::OUTPUT_START			=> 'aex_section_install_output_start',
			ExtensionListRendering::PRE_DISPLAY_AVAILABLE	=> 'aex_section_install_pre_display_available_ext_list',
			ExtensionListRendering::PRE_DISPLAY_INSTALLED	=> 'aex_section_manage_pre_display_installed_ext_list',
			ExtensionListRendering::END						=> 'aex_section_manage_end',
		),
		ExtensionListRendering::HOTFIXES	=> array(
			ExtensionListRendering::OUTPUT_START			=> 'aex_section_hotfixes_output_start',
			ExtensionListRendering::PRE_DISPLAY_AVAILABLE	=> 'aex_section_hotfixes_pre_display_available_ext_list',
			ExtensionListRendering::PRE_DISPLAY_INSTALLED	=> 'aex_section_hotfixes_pre_display_installed_ext_list',
			ExtensionListRendering::END						=> 'aex_section_hotfixes_end',
		),
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ExtensionListRendering $event): void {
		$point = self::POINTS[$event->list()][$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$boxes = $event->position() === ExtensionListRendering::PRE_DISPLAY_AVAILABLE;
		if ($boxes)
		{
			ForumPage::set('ext_item', self::group($event, ExtensionListRendering::AVAILABLE));
			ForumPage::set('ext_error', self::group($event, ExtensionListRendering::FAILED));
			ForumPage::set('item_num', 1 + $event->failedCount());
			$GLOBALS['num_exts'] = $event->availableCount();
			$GLOBALS['num_failed'] = $event->failedCount();
		}

		$event->append($this->scope->renderObserved($point, $event));

		if ($boxes)
		{
			self::replace($event, ExtensionListRendering::AVAILABLE, ForumPage::get('ext_item'));
			self::replace($event, ExtensionListRendering::FAILED, ForumPage::get('ext_error'));
			$event->count((int) Markers::markup($GLOBALS['num_exts'] ?? 0), (int) Markers::markup($GLOBALS['num_failed'] ?? 0));
		}
	}

	/** @return array<string, string> */
	private static function group(ExtensionListRendering $event, string $group): array {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[$name] = (string) $event->entry($group, $name);

		return $parts;
	}

	private static function replace(ExtensionListRendering $event, string $group, mixed $value): void {
		foreach ($event->names($group) as $name)
			$event->remove($group, $name);

		foreach (Markers::entries($value) as $name => $markup)
			$event->set($group, (string) $name, $markup);
	}
}
