<?php

declare(strict_types=1);

namespace PunBB\Module\Edit\Event;

use InvalidArgumentException;
use PunBB\Module\Edit\Api\Data\EditablePostInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of editing a post: the post selected and the visitor allowed; the
 * form submitted, before it is checked; the form checked, where an observer
 * may change the subject, the message and the errors that stop the edit; the
 * edit about to be stored; and the edit stored, before the browser is sent to
 * the post. Each error is markup.
 */
final class PostEditStep implements EventInterface {
	public const SELECTED = 'selected';

	public const SUBMITTED = 'submitted';

	public const VALIDATED = 'validated';

	public const EDITING = 'editing';

	public const EDITED = 'edited';

	private const STEPS = array(self::SELECTED, self::SUBMITTED, self::VALIDATED, self::EDITING, self::EDITED);

	/**
	 * @param ?string $subject the subject submitted, checked once validated; null when the post does not open its topic or nothing was submitted
	 * @param string $message the message submitted, checked once validated
	 * @param list<string> $errors
	 */
	public function __construct(
		private readonly string $step,
		private readonly EditablePostInterface $post,
		private readonly bool $moderating,
		private ?string $subject = null,
		private string $message = '',
		private bool $hidesSmilies = false,
		private array $errors = array()
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Editing a post has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function post(): EditablePostInterface {
		return $this->post;
	}

	/** Whether the visitor edits as a moderator of the post's forum. */
	public function moderating(): bool {
		return $this->moderating;
	}

	public function subject(): ?string {
		return $this->subject;
	}

	public function message(): string {
		return $this->message;
	}

	public function hidesSmilies(): bool {
		return $this->hidesSmilies;
	}

	/** @return list<string> */
	public function errors(): array {
		return $this->errors;
	}

	/** Changes what was submitted, until the form is checked. */
	public function change(?string $subject, string $message, bool $hidesSmilies): void {
		$this->guard();

		$this->subject = $this->post->isTopic() ? $subject : null;
		$this->message = $message;
		$this->hidesSmilies = $hidesSmilies;
	}

	/** @param list<string> $errors */
	public function setErrors(array $errors): void {
		$this->guard();

		$this->errors = $errors;
	}

	private function guard(): void {
		if (!in_array($this->step, array(self::SUBMITTED, self::VALIDATED), true))
			throw new InvalidArgumentException(sprintf('An edit is past changing at step "%s"', $this->step));
	}
}
