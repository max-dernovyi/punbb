<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Message;

/**
 * The message redirect() gave extension code, kept from the moment a redirect
 * is shown for the points after it.
 */
final class ShownRedirect {
	public string $message = '';
}
