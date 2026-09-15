<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Api;

use PunBB\Module\Misc\Api\Data\MailSentInterface;
use PunBB\Module\Misc\Api\Data\RecipientInterface;

/**
 * The members mailed through the board's form, and when the senders last mailed.
 */
interface RecipientsInterface {
	public function find(int $userId): ?RecipientInterface;

	public function recordMailSent(MailSentInterface ...$sent): void;
}
