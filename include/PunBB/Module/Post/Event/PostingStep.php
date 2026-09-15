<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Post\Api\Data\LocationInterface;
use PunBB\Module\Post\Api\Data\NewPostInterface;

/**
 * A step of posting: where the visitor posts selected and the visitor allowed;
 * the form submitted, before it is checked; the form checked, where an
 * observer may change the name, the address, the subject, the message and
 * the options it carries; the post about to be stored, where an observer may
 * replace it; and the post stored, before the browser is sent to it. Until the
 * form is checked an observer may change the errors that stop it; each is markup.
 */
final class PostingStep implements EventInterface {
	public const SELECTED = 'selected';

	public const SUBMITTED = 'submitted';

	public const VALIDATED = 'validated';

	public const ADDING = 'adding';

	public const ADDED = 'added';

	private const STEPS = array(self::SELECTED, self::SUBMITTED, self::VALIDATED, self::ADDING, self::ADDED);

	/**
	 * @param list<string> $errors
	 * @param ?string $subject the new topic's subject once the form is checked; null for a reply
	 * @param int $postId the post's id, once stored
	 * @param int $topicId the new topic's id, once stored; 0 for a reply
	 */
	public function __construct(
		private readonly string $step,
		private readonly LocationInterface $location,
		private readonly bool $moderating,
		private array $errors = array(),
		private string $username = '',
		private string $email = '',
		private ?string $subject = null,
		private string $message = '',
		private bool $hidesSmilies = false,
		private bool $subscribes = false,
		private ?NewPostInterface $post = null,
		private readonly int $postId = 0,
		private readonly int $topicId = 0
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Posting has no step "%s"', $step));

		if (in_array($step, array(self::ADDING, self::ADDED), true) && $post === null)
			throw new InvalidArgumentException(sprintf('Posting carries its post at step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function location(): LocationInterface {
		return $this->location;
	}

	/** Whether the visitor posts as a moderator of the forum. */
	public function moderating(): bool {
		return $this->moderating;
	}

	/** @return list<string> */
	public function errors(): array {
		return $this->errors;
	}

	public function username(): string {
		return $this->username;
	}

	public function email(): string {
		return $this->email;
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

	/** Whether the visitor asked to be subscribed to the topic. */
	public function subscribes(): bool {
		return $this->subscribes;
	}

	public function post(): ?NewPostInterface {
		return $this->post;
	}

	public function postId(): int {
		return $this->postId;
	}

	public function topicId(): int {
		return $this->topicId;
	}

	/** Changes what the checked form carries. */
	public function change(string $username, string $email, ?string $subject, string $message, bool $hidesSmilies, bool $subscribes): void {
		if ($this->step !== self::VALIDATED)
			throw new InvalidArgumentException(sprintf('A post\'s form is not changed at step "%s"', $this->step));

		$this->username = $username;
		$this->email = $email;
		$this->subject = $this->location->topicId() === 0 ? $subject ?? '' : null;
		$this->message = $message;
		$this->hidesSmilies = $hidesSmilies;
		$this->subscribes = $subscribes;
	}

	/** @param list<string> $errors */
	public function setErrors(array $errors): void {
		if (!in_array($this->step, array(self::SUBMITTED, self::VALIDATED), true))
			throw new InvalidArgumentException(sprintf('A post has no errors to change at step "%s"', $this->step));

		$this->errors = $errors;
	}

	public function replacePost(NewPostInterface $post): void {
		if ($this->step !== self::ADDING)
			throw new InvalidArgumentException('A post is replaced only before it is stored');

		$this->post = $post;
	}
}
