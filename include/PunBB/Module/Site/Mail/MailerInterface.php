<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Mail;

/**
 * Sends the board's mail, from the board's own address.
 */
interface MailerInterface {
	/**
	 * Sends $message to the addresses in $to, separated by commas; one that is
	 * not a valid address is dropped.
	 *
	 * @param bool $quiet whether a relay that refuses the mail is left unreported, where reporting it would tell the visitor something
	 * @param string $replyTo the address a reply goes to; '' for the board's own
	 * @param string $replyToName the name shown with it
	 */
	public function send(string $to, string $subject, string $message, bool $quiet = false, string $replyTo = '', string $replyToName = ''): void;
}
