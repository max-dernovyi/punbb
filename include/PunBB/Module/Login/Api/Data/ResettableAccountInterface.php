<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Api\Data;

/**
 * An account a new password may be mailed for.
 */
interface ResettableAccountInterface {
	public function id(): int;

	public function groupId(): int;

	public function username(): string;

	/** When a reset key was last mailed for it; null when none was. */
	public function lastEmailSent(): ?int;
}
