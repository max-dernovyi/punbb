<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Api\Data;

/**
 * When a member last sent mail or a report through the board, which floods are measured from.
 */
interface MailSentInterface {
	public function userId(): int;

	public function at(): int;
}
