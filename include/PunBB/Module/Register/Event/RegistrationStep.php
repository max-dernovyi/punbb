<?php

declare(strict_types=1);

namespace PunBB\Module\Register\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Register\Api\Data\NewAccountInterface;

/**
 * A step of registering: the form submitted, before anything is checked; the
 * form checked, with the name, address and password it carries; the account
 * about to be stored, where an observer may replace it; and the account
 * stored, before the visitor is signed in or told to verify it. Until the
 * form is checked an observer may change the errors that stop it; each is markup.
 */
final class RegistrationStep implements EventInterface {
	public const SUBMITTED = 'submitted';

	public const VALIDATED = 'validated';

	public const ADDING = 'adding';

	public const ADDED = 'added';

	private const STEPS = array(self::SUBMITTED, self::VALIDATED, self::ADDING, self::ADDED);

	/**
	 * @param list<string> $errors
	 * @param int $userId the account's id, once stored
	 */
	public function __construct(
		private readonly string $step,
		private array $errors = array(),
		private readonly string $username = '',
		private readonly string $email = '',
		private readonly string $password = '',
		private ?NewAccountInterface $account = null,
		private readonly int $userId = 0
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Registering has no step "%s"', $step));

		if (in_array($step, array(self::ADDING, self::ADDED), true) && $account === null)
			throw new InvalidArgumentException(sprintf('Registering carries its account at step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	/** @return list<string> */
	public function errors(): array {
		return $this->errors;
	}

	public function username(): string {
		return $this->account?->username() ?? $this->username;
	}

	public function email(): string {
		return $this->account?->email() ?? $this->email;
	}

	public function password(): string {
		return $this->account?->password() ?? $this->password;
	}

	public function account(): ?NewAccountInterface {
		return $this->account;
	}

	public function userId(): int {
		return $this->userId;
	}

	/** @param list<string> $errors */
	public function setErrors(array $errors): void {
		if (!in_array($this->step, array(self::SUBMITTED, self::VALIDATED), true))
			throw new InvalidArgumentException(sprintf('A registration has no errors to change at step "%s"', $this->step));

		$this->errors = $errors;
	}

	public function replaceAccount(NewAccountInterface $account): void {
		if ($this->step !== self::ADDING)
			throw new InvalidArgumentException('An account is replaced only before it is stored');

		$this->account = $account;
	}
}
