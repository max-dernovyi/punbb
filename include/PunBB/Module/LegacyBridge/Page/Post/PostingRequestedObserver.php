<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Post;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Post\Event\PostingRequested;

/**
 * Runs po_start.
 */
final class PostingRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(PostingRequested $event): void {
		$this->scope->observe('po_start', $event);
	}
}
