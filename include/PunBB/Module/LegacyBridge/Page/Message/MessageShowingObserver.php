<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Message;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Message\Event\MessageShowing;

/**
 * Runs fn_message_start with the message, the link and the heading as message()'s locals.
 */
final class MessageShowingObserver {
	public function __construct(private readonly PageScope $scope, private readonly ShownMessage $shown) {}

	public function observe(MessageShowing $event): void {
		$message = $event->message();
		$link = $event->link();
		$heading = $event->heading();

		$this->scope->observe('fn_message_start', $event, array('message' => &$message, 'link' => &$link, 'heading' => &$heading));

		$event->change(Markers::markup($message), Markers::markup($link), Markers::markup($heading));

		$this->shown->message = $event->message();
		$this->shown->link = $event->link();
		$this->shown->heading = $event->heading();
	}
}
