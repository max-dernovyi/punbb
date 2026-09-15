<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Login\Api\Data\CredentialsInterface;

/**
 * A step of signing in: the form submitted, before the account is looked up;
 * the password checked, where an observer may change whether the visitor is
 * let in; and the visitor signed in, before the browser is sent on. Until the
 * password is checked an observer may add errors that stop it; each is markup.
 */
final class LoginStep implements EventInterface {
	public const SUBMITTED = 'submitted';

	public const CHECKED = 'checked';

	public const SIGNED_IN = 'signed_in';

	private const STEPS = array(self::SUBMITTED, self::CHECKED, self::SIGNED_IN);

	/**
	 * @param ?CredentialsInterface $credentials the account the username names, once looked up; null when there is none
	 * @param list<string> $errors
	 */
	public function __construct(
		private readonly string $step,
		private readonly string $username,
		private readonly bool $savesPassword,
		private readonly ?CredentialsInterface $credentials = null,
		private bool $authorized = false,
		private array $errors = array()
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Signing in has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function username(): string {
		return $this->username;
	}

	/** Whether the visitor asked to stay signed in. */
	public function savesPassword(): bool {
		return $this->savesPassword;
	}

	public function credentials(): ?CredentialsInterface {
		return $this->credentials;
	}

	/** Whether the password was the account's. */
	public function authorized(): bool {
		return $this->authorized;
	}

	/** @return list<string> */
	public function errors(): array {
		return $this->errors;
	}

	public function authorize(bool $authorized): void {
		if ($this->step !== self::CHECKED || ($authorized && $this->credentials === null))
			throw new InvalidArgumentException('Only a password checked against an account decides whether the visitor is let in');

		$this->authorized = $authorized;
	}

	/** @param list<string> $errors */
	public function setErrors(array $errors): void {
		if ($this->step === self::SIGNED_IN)
			throw new InvalidArgumentException('A visitor signed in has no errors to change');

		$this->errors = $errors;
	}
}
