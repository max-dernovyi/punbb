<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Event\ExtensionActionsAssembling;
use PunBB\Module\Extensions\Event\ExtensionListRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the point before an installed extension's links are joined, with the
 * extension in $id and its row in $ext, and the links in
 * $forum_page['ext_actions'], read back.
 */
final class ExtensionActionsObserver {
	public const POINTS = array(
		ExtensionListRendering::MANAGE		=> 'aex_section_manage_pre_ext_actions',
		ExtensionListRendering::HOTFIXES	=> 'aex_section_hotfixes_pre_ext_actions',
	);

	public function __construct(private readonly PageScope $scope, private readonly ExtensionRows $rows) {}

	public function observe(ExtensionActionsAssembling $event): void {
		$point = self::POINTS[$event->list()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['id'] = $event->extension()->id();
		$GLOBALS['ext'] = $this->rows->extension($event->extension());

		$actions = array();
		foreach ($event->names() as $name)
			$actions[$name] = (string) $event->entry($name);

		ForumPage::set('ext_actions', $actions);

		$event->append($this->scope->renderObserved($point, $event));

		foreach ($event->names() as $name)
			$event->remove($name);

		foreach (Markers::entries(ForumPage::get('ext_actions')) as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
