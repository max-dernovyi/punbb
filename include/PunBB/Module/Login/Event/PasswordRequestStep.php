<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of asking for a new password: the page selected for a guest, and the
 * submitted address checked, where an observer may change the errors that stop
 * the request; each is markup.
 */
final class PasswordRequestStep implements EventInterface {
	public const SELECTED = 'selected';

	public const VALIDATED = 'validated';

	/** @param list<string> $errors */
	public function __construct(private readonly string $step, private readonly string $email = '', private array $errors = array()) {
		if (!in_array($step, array(self::SELECTED, self::VALIDATED), true))
			throw new InvalidArgumentException(sprintf('Asking for a new password has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function email(): string {
		return $this->email;
	}

	/** @return list<string> */
	public function errors(): array {
		return $this->errors;
	}

	/** @param list<string> $errors */
	public function setErrors(array $errors): void {
		if ($this->step !== self::VALIDATED)
			throw new InvalidArgumentException('Nothing was submitted to have errors');

		$this->errors = $errors;
	}
}
