<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Misc\Api\Data\RecipientInterface;

/**
 * A step of mailing a member through the board's form: the recipient asked
 * for, before they are looked up; the form submitted; the subject and message
 * checked, where an observer may change the errors that stop the mail; the
 * mail composed from its template, where an observer may change its subject
 * and message; and the mail sent, before the browser is sent on. Each error is markup.
 */
final class EmailStep implements EventInterface {
	public const SELECTED = 'selected';

	public const SUBMITTED = 'submitted';

	public const VALIDATED = 'validated';

	public const COMPOSED = 'composed';

	public const SENT = 'sent';

	private const STEPS = array(self::SELECTED, self::SUBMITTED, self::VALIDATED, self::COMPOSED, self::SENT);

	/**
	 * @param ?RecipientInterface $recipient the member mailed, once looked up
	 * @param string $subject the subject the sender typed, once submitted
	 * @param string $message the message the sender typed, once submitted
	 * @param list<string> $errors
	 * @param string $mailSubject the mail's subject, once composed
	 * @param string $mailMessage the mail's message, once composed
	 */
	public function __construct(
		private readonly string $step,
		private readonly int $recipientId,
		private readonly ?RecipientInterface $recipient = null,
		private readonly string $subject = '',
		private readonly string $message = '',
		private array $errors = array(),
		private string $mailSubject = '',
		private string $mailMessage = ''
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Mailing a member has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function recipientId(): int {
		return $this->recipientId;
	}

	public function recipient(): ?RecipientInterface {
		return $this->recipient;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function message(): string {
		return $this->message;
	}

	/** @return list<string> */
	public function errors(): array {
		return $this->errors;
	}

	public function mailSubject(): string {
		return $this->mailSubject;
	}

	public function mailMessage(): string {
		return $this->mailMessage;
	}

	/** @param list<string> $errors */
	public function setErrors(array $errors): void {
		if ($this->step !== self::VALIDATED)
			throw new InvalidArgumentException('Only the checked mail\'s errors change');

		$this->errors = $errors;
	}

	public function compose(string $subject, string $message): void {
		if ($this->step !== self::COMPOSED)
			throw new InvalidArgumentException('Only the composed mail\'s subject and message change');

		$this->mailSubject = $subject;
		$this->mailMessage = $message;
	}
}
