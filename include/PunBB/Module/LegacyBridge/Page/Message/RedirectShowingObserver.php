<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Message;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Message\Event\RedirectShowing;

/**
 * Defines FORUM_PAGE as redirect() did first thing, then runs fn_redirect_start
 * with the destination and the message as its locals.
 */
final class RedirectShowingObserver {
	public function __construct(private readonly PageScope $scope, private readonly ShownRedirect $shown) {}

	public function observe(RedirectShowing $event): void {
		if (!defined('FORUM_PAGE'))
			define('FORUM_PAGE', 'redirect');

		$destination_url = $event->destination();
		$message = $event->message();

		$this->scope->observe('fn_redirect_start', $event, array('destination_url' => &$destination_url, 'message' => &$message));

		$event->change(Markers::markup($destination_url), Markers::markup($message));

		$this->shown->message = $event->message();
	}
}
