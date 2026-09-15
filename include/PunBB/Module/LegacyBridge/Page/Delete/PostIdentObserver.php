<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Delete;

use PunBB\Module\Delete\Event\PostIdentAssembling;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs dl_pre_item_ident_merge with the heading's parts as $forum_page['post_ident'], read back.
 */
final class PostIdentObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(PostIdentAssembling $event): void {
		if (!LegacyScope::attached('dl_pre_item_ident_merge'))
			return;

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page['post_ident'] = array();
		foreach ($event->names() as $name)
			$page['post_ident'][$name] = (string) $event->entry($name);
		$GLOBALS['forum_page'] = $page;

		$this->scope->observe('dl_pre_item_ident_merge', $event);

		$parts = Markers::entries(is_array($GLOBALS['forum_page'] ?? null) ? ($GLOBALS['forum_page']['post_ident'] ?? null) : null);

		foreach ($event->names() as $name)
			if (!isset($parts[$name]))
				$event->remove($name);

		foreach ($parts as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
