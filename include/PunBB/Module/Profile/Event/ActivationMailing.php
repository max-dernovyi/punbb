<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * The mail carrying the key that confirms a new address is composed from its
 * template, before it is sent there: an observer may change the subject and
 * the message.
 */
final class ActivationMailing implements EventInterface {
	public function __construct(
		private readonly ProfileUserInterface $user,
		private readonly string $email,
		private readonly string $key,
		private string $subject,
		private string $message
	) {}

	public function user(): ProfileUserInterface {
		return $this->user;
	}

	public function email(): string {
		return $this->email;
	}

	public function key(): string {
		return $this->key;
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
