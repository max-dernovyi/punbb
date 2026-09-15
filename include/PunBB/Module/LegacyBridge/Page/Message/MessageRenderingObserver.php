<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Message;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Message\Event\MessageRendering;

/**
 * Renders fn_message_output_start and fn_message_output_end at their positions around a message.
 */
final class MessageRenderingObserver {
	public function __construct(private readonly PageScope $scope, private readonly ShownMessage $shown) {}

	public function observe(MessageRendering $event): void {
		$message = $this->shown->message;
		$link = $this->shown->link;
		$heading = $this->shown->heading;
		$locals = array('message' => &$message, 'link' => &$link, 'heading' => &$heading);

		$event->append($event->position() === MessageRendering::START
			? $this->scope->renderObserved('fn_message_output_start', $event, $locals)
			: $this->scope->renderObserved('fn_message_output_end', $event, $locals));
	}
}
