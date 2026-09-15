<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Flash;

use PunBB\Module\Layout\View\Html;

/**
 * The messages the visitor's next page shows, once: what the action they took did.
 */
interface FlashMessagesInterface {
	public function info(Html $message): void;
}
