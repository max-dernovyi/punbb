<?php

declare(strict_types=1);

namespace PunBB\Module\Message\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * The reply a script gets instead of a redirect page or a confirmation form,
 * about to be sent as JSON: its code and message, and where the redirect leads
 * or the token, the previous page and the fields, by name, the form would post
 * again.
 */
final class RedirectJsonSending implements EventInterface {
	use MarkupEntries;

	public const REDIRECT = -2;

	public const CONFIRMATION = -3;

	private function __construct(
		private int $code,
		private string $message,
		private ?string $destination,
		private ?string $token,
		private ?string $previousUrl
	) {}

	public static function redirect(string $message, string $destination): self {
		return new self(self::REDIRECT, $message, $destination, null, null);
	}

	/** A confirmation's reply; the fields it would post again are set on it, each value encoded for an attribute. */
	public static function confirmation(string $message, string $token, string $previousUrl): self {
		return new self(self::CONFIRMATION, $message, null, $token, $previousUrl);
	}

	public function code(): int {
		return $this->code;
	}

	public function message(): string {
		return $this->message;
	}

	/** Where a redirect leads; null for a confirmation. */
	public function destination(): ?string {
		return $this->destination;
	}

	/** The token a confirmation posts with; null for a redirect. */
	public function token(): ?string {
		return $this->token;
	}

	/** The page a confirmation returns to when cancelled; null for a redirect. */
	public function previousUrl(): ?string {
		return $this->previousUrl;
	}

	public function change(int $code, string $message): void {
		$this->code = $code;
		$this->message = $message;
	}

	public function changeDestination(string $destination): void {
		if ($this->destination === null)
			throw new InvalidArgumentException('A confirmation has no destination');

		$this->destination = $destination;
	}

	public function changeConfirmation(string $token, string $previousUrl): void {
		if ($this->token === null)
			throw new InvalidArgumentException('A redirect carries no confirmation');

		$this->token = $token;
		$this->previousUrl = $previousUrl;
	}

	private function accept(string $name): void {
		if ($this->token === null)
			throw new InvalidArgumentException('A redirect posts nothing again');
	}
}
