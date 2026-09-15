<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Post;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Post\Event\PostPreviewAssembling;

/**
 * Renders po_preview_pre_display with the preview's heading in
 * $forum_page['post_ident'] and its message in ['preview_message'], both read back.
 */
final class PostPreviewObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(PostPreviewAssembling $event): void {
		if (!LegacyScope::attached('po_preview_pre_display'))
			return;

		$ident = array();
		foreach ($event->names() as $name)
			$ident[$name] = (string) $event->entry($name);

		ForumPage::set('post_ident', $ident);
		ForumPage::set('preview_message', $event->message());

		$event->append($this->scope->renderObserved('po_preview_pre_display', $event));

		$ident = Markers::entries(ForumPage::get('post_ident'));
		foreach ($event->names() as $name)
			if (!isset($ident[$name]))
				$event->remove($name);

		foreach ($ident as $name => $markup)
			$event->set((string) $name, $markup);

		$event->setMessage(Markers::markup(ForumPage::get('preview_message')));
	}
}
