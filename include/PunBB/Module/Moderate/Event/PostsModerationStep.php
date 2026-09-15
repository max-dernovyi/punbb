<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedTopicInterface;

/**
 * A step of moderating a topic's posts: the topic asked for, before it is
 * read; deleting posts or splitting them off, once asked for, before the
 * selection is read, once confirmed, before it is checked, and once done,
 * before the browser is sent on.
 */
final class PostsModerationStep implements EventInterface {
	/** Carries nothing but the step. */
	public const SELECTED = 'selected';

	/** Carries the topic. */
	public const DELETE_SUBMITTED = 'delete_submitted';

	/** Carries the topic and the posts selected. */
	public const DELETE_CONFIRMED = 'delete_confirmed';

	public const DELETED = 'deleted';

	public const SPLIT_SUBMITTED = 'split_submitted';

	public const SPLIT_CONFIRMED = 'split_confirmed';

	/** Carries the topic, the posts split off, and the new topic's id and subject. */
	public const SPLIT = 'split';

	private const STEPS = array(self::SELECTED, self::DELETE_SUBMITTED, self::DELETE_CONFIRMED, self::DELETED, self::SPLIT_SUBMITTED, self::SPLIT_CONFIRMED, self::SPLIT);

	/** @param list<int> $postIds */
	public function __construct(
		private readonly string $step,
		private readonly ?ModeratedTopicInterface $topic = null,
		private readonly array $postIds = array(),
		private readonly int $newTopicId = 0,
		private readonly string $newSubject = ''
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Moderating posts has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	/** The topic; null while it is not read. */
	public function topic(): ?ModeratedTopicInterface {
		return $this->topic;
	}

	/** @return list<int> */
	public function postIds(): array {
		return $this->postIds;
	}

	/** The topic the posts were split off into; 0 before they are. */
	public function newTopicId(): int {
		return $this->newTopicId;
	}

	public function newSubject(): string {
		return $this->newSubject;
	}
}
