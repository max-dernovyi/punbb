<?php

declare(strict_types=1);

namespace PunBB\Module\Register\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Register\Api\Data\NewAccountInterface;

/**
 * The mailing list is about to be told that an account was registered with a
 * banned address, or with an address other accounts have; an observer may
 * change the subject and the message.
 */
final class RegistrationAlerting implements EventInterface {
	public const BANNED_EMAIL = 'banned_email';

	public const DUPLICATE_EMAIL = 'duplicate_email';

	/** @param list<string> $duplicates the other accounts registered with the address */
	public function __construct(
		private readonly string $alert,
		private readonly NewAccountInterface $account,
		private readonly int $userId,
		private readonly array $duplicates,
		private string $subject,
		private string $message
	) {
		if (!in_array($alert, array(self::BANNED_EMAIL, self::DUPLICATE_EMAIL), true))
			throw new InvalidArgumentException(sprintf('A registration raises no alert "%s"', $alert));
	}

	public function alert(): string {
		return $this->alert;
	}

	public function account(): NewAccountInterface {
		return $this->account;
	}

	public function userId(): int {
		return $this->userId;
	}

	/** @return list<string> */
	public function duplicates(): array {
		return $this->duplicates;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function message(): string {
		return $this->message;
	}

	public function compose(string $subject, string $message): void {
		$this->subject = $subject;
		$this->message = $message;
	}
}
