<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Misc\Api\Data\ReportedTopicInterface;

/**
 * A step of reporting a post: the post asked for; the form submitted, before
 * the reason is checked; the report about to be filed, once the post's topic
 * is found; the report about to be mailed to the board's mailing list, where
 * an observer may change the mail's subject and message; and the report filed,
 * before the browser is sent on.
 */
final class ReportStep implements EventInterface {
	public const SELECTED = 'selected';

	public const SUBMITTED = 'submitted';

	public const REPORTING = 'reporting';

	public const MAILING = 'mailing';

	public const REPORTED = 'reported';

	private const STEPS = array(self::SELECTED, self::SUBMITTED, self::REPORTING, self::MAILING, self::REPORTED);

	/**
	 * @param string $reason the reason given, once checked
	 * @param ?ReportedTopicInterface $topic the post's topic, once found
	 */
	public function __construct(
		private readonly string $step,
		private readonly int $postId,
		private readonly string $reason = '',
		private readonly ?ReportedTopicInterface $topic = null,
		private string $mailSubject = '',
		private string $mailMessage = ''
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Reporting a post has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function postId(): int {
		return $this->postId;
	}

	public function reason(): string {
		return $this->reason;
	}

	public function topic(): ?ReportedTopicInterface {
		return $this->topic;
	}

	public function mailSubject(): string {
		return $this->mailSubject;
	}

	public function mailMessage(): string {
		return $this->mailMessage;
	}

	public function compose(string $subject, string $message): void {
		if ($this->step !== self::MAILING)
			throw new InvalidArgumentException('Only the report being mailed changes its subject and message');

		$this->mailSubject = $subject;
		$this->mailMessage = $message;
	}
}
