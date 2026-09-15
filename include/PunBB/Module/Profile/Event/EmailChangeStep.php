<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * A step of changing a member's address: the change asked for; the key a
 * confirmation mail carries supplied, before it is checked; the form
 * submitted, before it is checked; the address found banned; and the address
 * found among other members'. From the form on, an observer may change the
 * errors that stop the change, each markup.
 */
final class EmailChangeStep implements EventInterface {
	public const SELECTED = 'selected';

	public const KEY_SUPPLIED = 'key_supplied';

	public const SUBMITTED = 'submitted';

	public const BANNED = 'banned';

	public const DUPLICATE = 'duplicate';

	private const STEPS = array(self::SELECTED, self::KEY_SUPPLIED, self::SUBMITTED, self::BANNED, self::DUPLICATE);

	/**
	 * @param string $email the address asked for, once checked
	 * @param list<string> $errors
	 * @param list<string> $duplicates the other members registered with the address
	 */
	public function __construct(
		private readonly string $step,
		private readonly ProfileUserInterface $user,
		private readonly string $key = '',
		private readonly string $email = '',
		private array $errors = array(),
		private readonly array $duplicates = array()
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Changing an address has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function user(): ProfileUserInterface {
		return $this->user;
	}

	public function key(): string {
		return $this->key;
	}

	public function email(): string {
		return $this->email;
	}

	/** @return list<string> */
	public function errors(): array {
		return $this->errors;
	}

	/** @return list<string> */
	public function duplicates(): array {
		return $this->duplicates;
	}

	/** @param list<string> $errors */
	public function setErrors(array $errors): void {
		if (in_array($this->step, array(self::SELECTED, self::KEY_SUPPLIED), true))
			throw new InvalidArgumentException('An address change\'s errors change once its form is submitted only');

		$this->errors = $errors;
	}
}
