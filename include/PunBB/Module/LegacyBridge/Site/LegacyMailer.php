<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\Site\Mail\MailerInterface;

/**
 * forum_mail() of include/email.php, with the extension code attached to it.
 */
final class LegacyMailer implements MailerInterface {
	public function send(string $to, string $subject, string $message, bool $quiet = false, string $replyTo = '', string $replyToName = ''): void {
		if (!defined('FORUM_EMAIL_FUNCTIONS_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/email.php');

		\forum_mail($to, $subject, $message, $replyTo, $replyToName, $quiet);
	}
}
