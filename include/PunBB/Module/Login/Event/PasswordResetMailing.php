<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Login\Api\Data\ResettableAccountInterface;

/**
 * A step of mailing reset keys, once the visitor has their answer: the
 * accounts of the address found; the mail composed from its template, where an
 * observer may change the subject and the message; an account about to get a
 * key, where an observer may change how long the last key keeps a second one
 * from being sent; and the mail addressed to an account, where an observer may
 * change the message. An administrator's account gets no key.
 */
final class PasswordResetMailing implements EventInterface {
	public const STARTING = 'starting';

	public const COMPOSED = 'composed';

	public const CHECKING = 'checking';

	public const ADDRESSED = 'addressed';

	private const STEPS = array(self::STARTING, self::COMPOSED, self::CHECKING, self::ADDRESSED);

	/**
	 * @param list<ResettableAccountInterface> $accounts
	 * @param int $keyLifetime seconds a key keeps a second one from being mailed
	 */
	public function __construct(
		private readonly string $step,
		private readonly string $email,
		private readonly array $accounts,
		private string $subject = '',
		private string $message = '',
		private readonly ?ResettableAccountInterface $account = null,
		private int $keyLifetime = 0,
		private readonly string $key = ''
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Mailing reset keys has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function email(): string {
		return $this->email;
	}

	/** @return list<ResettableAccountInterface> */
	public function accounts(): array {
		return $this->accounts;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function message(): string {
		return $this->message;
	}

	/** The account a key is mailed for, from checking on. */
	public function account(): ?ResettableAccountInterface {
		return $this->account;
	}

	public function keyLifetime(): int {
		return $this->keyLifetime;
	}

	/** The key mailed, once addressed. */
	public function key(): string {
		return $this->key;
	}

	public function compose(string $subject, string $message): void {
		if ($this->step !== self::COMPOSED)
			throw new InvalidArgumentException('Only the composed mail\'s subject changes');

		$this->subject = $subject;
		$this->message = $message;
	}

	public function setKeyLifetime(int $seconds): void {
		if ($this->step !== self::CHECKING)
			throw new InvalidArgumentException('A key\'s lifetime changes only while its account is checked');

		$this->keyLifetime = $seconds;
	}

	public function address(string $message): void {
		if ($this->step !== self::ADDRESSED)
			throw new InvalidArgumentException('Only the mail addressed to an account changes its message');

		$this->message = $message;
	}
}
