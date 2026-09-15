<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Message;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Message\Event\RedirectHeadAssembling;

/**
 * Runs fn_redirect_head with the head entries as $forum_head.
 */
final class RedirectHeadObserver {
	public function __construct(private readonly PageScope $scope, private readonly ShownRedirect $shown) {}

	public function observe(RedirectHeadAssembling $event): void {
		if (!LegacyScope::attached('fn_redirect_head'))
			return;

		$forum_head = array();
		foreach ($event->names() as $name)
			$forum_head[$name] = (string) $event->entry($name);

		$message = $this->shown->message;

		$this->scope->observe('fn_redirect_head', $event, array('forum_head' => &$forum_head, 'message' => &$message));

		foreach ($event->names() as $name)
			$event->remove($name);

		foreach (Markers::entries($forum_head) as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
