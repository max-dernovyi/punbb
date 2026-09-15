<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\Site\Mail\EmailAddressesInterface;

/**
 * is_valid_email() and is_banned_email() of include/email.php, with the extension code attached to them.
 */
final class LegacyEmailAddresses implements EmailAddressesInterface {
	public function isValid(string $address): bool {
		if (!defined('FORUM_EMAIL_FUNCTIONS_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/email.php');

		return (bool) \is_valid_email($address);
	}

	public function isBanned(string $address): bool {
		if (!defined('FORUM_EMAIL_FUNCTIONS_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/email.php');

		return (bool) \is_banned_email($address);
	}
}
