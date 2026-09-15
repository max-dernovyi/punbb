<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Post;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Post\Event\QuoteSelected;

/**
 * Runs po_modify_quote_info with the post quoted in $quote_info, with any
 * column a query point added, its poster and message read back.
 */
final class QuoteSelectedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(QuoteSelected $event): void {
		if (!LegacyScope::attached('po_modify_quote_info'))
			return;

		$row = is_array($GLOBALS['quote_info'] ?? null) ? $GLOBALS['quote_info'] : array();
		$GLOBALS['quote_info'] = array('poster' => $event->poster(), 'message' => $event->message()) + $row;

		$this->scope->observe('po_modify_quote_info', $event);

		$quote = is_array($GLOBALS['quote_info'] ?? null) ? $GLOBALS['quote_info'] : array();
		$event->change(Markers::markup($quote['poster'] ?? ''), Markers::markup($quote['message'] ?? ''));
	}
}
