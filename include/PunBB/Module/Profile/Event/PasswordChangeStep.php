<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * A step of changing a member's password: the change asked for, before
 * anything is checked; the key a reset mail carries supplied, before it is
 * checked; the form submitted, before it is checked, where an observer may
 * add errors that stop the change, each markup; and the password stored,
 * before the browser is sent on.
 */
final class PasswordChangeStep implements EventInterface {
	public const SELECTED = 'selected';

	public const KEY_SUPPLIED = 'key_supplied';

	public const SUBMITTED = 'submitted';

	public const CHANGED = 'changed';

	private const STEPS = array(self::SELECTED, self::KEY_SUPPLIED, self::SUBMITTED, self::CHANGED);

	/**
	 * @param bool $withKey whether the password is reset with the key a mail carries, by a guest
	 * @param string $key the key supplied; '' without one
	 * @param list<string> $errors
	 * @param string $hash the password as stored, once changed
	 */
	public function __construct(
		private readonly string $step,
		private readonly ProfileUserInterface $user,
		private readonly bool $withKey,
		private readonly string $key = '',
		private array $errors = array(),
		private readonly string $hash = ''
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Changing a password has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function user(): ProfileUserInterface {
		return $this->user;
	}

	public function withKey(): bool {
		return $this->withKey;
	}

	public function key(): string {
		return $this->key;
	}

	/** @return list<string> */
	public function errors(): array {
		return $this->errors;
	}

	public function hash(): string {
		return $this->hash;
	}

	/** @param list<string> $errors */
	public function setErrors(array $errors): void {
		if ($this->step !== self::SUBMITTED)
			throw new InvalidArgumentException('A password change\'s errors change while its form is submitted only');

		$this->errors = $errors;
	}
}
