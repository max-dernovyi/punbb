<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use FlashMessenger;
use PunBB\Module\Layout\Chrome\ChromeException;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Site\Flash\FlashMessagesInterface;

/**
 * The flash messenger include/essentials.php left in $forum_flash.
 */
final class LegacyFlashMessages implements FlashMessagesInterface {
	public function info(Html $message): void {
		$flash = $GLOBALS['forum_flash'] ?? null;
		if (!$flash instanceof FlashMessenger)
			throw new ChromeException('The legacy bootstrap has no flash messenger in $forum_flash');

		$flash->add_info($message->html);
	}
}
