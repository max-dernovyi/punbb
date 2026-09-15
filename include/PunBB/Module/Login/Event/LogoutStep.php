<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of signing out: the member's link checked out, and the member signed
 * out, before the browser is sent to the index.
 */
final class LogoutStep implements EventInterface {
	public const SELECTED = 'selected';

	public const SIGNED_OUT = 'signed_out';

	public function __construct(private readonly string $step, private readonly int $userId) {
		if (!in_array($step, array(self::SELECTED, self::SIGNED_OUT), true))
			throw new InvalidArgumentException(sprintf('Signing out has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function userId(): int {
		return $this->userId;
	}
}
