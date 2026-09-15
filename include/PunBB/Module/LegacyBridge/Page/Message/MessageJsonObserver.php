<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Message;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Message\Event\MessageJsonSending;

/**
 * Runs fn_message_pre_send_json with the reply as $json_data; its code and message are read back.
 */
final class MessageJsonObserver {
	public function __construct(private readonly PageScope $scope, private readonly ShownMessage $shown) {}

	public function observe(MessageJsonSending $event): void {
		/** @var mixed $json_data the hook may leave anything in it */
		$json_data = array('code' => $event->code(), 'message' => $event->message());
		$message = $this->shown->message;
		$link = $this->shown->link;
		$heading = $this->shown->heading;

		$this->scope->observe('fn_message_pre_send_json', $event, array('json_data' => &$json_data, 'message' => &$message, 'link' => &$link, 'heading' => &$heading));

		$event->change(is_array($json_data) ? (int) Markers::markup($json_data['code'] ?? $event->code()) : $event->code(),
			is_array($json_data) ? Markers::markup($json_data['message'] ?? $event->message()) : $event->message());
	}
}
