<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Message;

/**
 * The locals message() gave extension code, kept from the moment a message is
 * shown for the points after it.
 */
final class ShownMessage {
	public string $message = '';

	public string $link = '';

	public string $heading = '';
}
