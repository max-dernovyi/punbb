<?php

declare(strict_types=1);

namespace PunBB\Module\Delete\Event;

use InvalidArgumentException;
use PunBB\Module\Delete\Api\Data\DeletablePostInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of a deletion the visitor may make: the post selected, the form
 * submitted, the topic or the post deleted and the browser about to be sent on.
 */
final class PostDeletionStep implements EventInterface {
	/** The visitor may delete the post; nothing was submitted yet. */
	public const SELECTED = 'selected';

	/** The deletion form was submitted, before its confirmation is checked. */
	public const SUBMITTED = 'submitted';

	/** The topic is deleted, before the browser is sent to its forum. */
	public const TOPIC_DELETED = 'topic_deleted';

	/** The post is deleted, before the browser is sent to the post before it. */
	public const POST_DELETED = 'post_deleted';

	private const STEPS = array(self::SELECTED, self::SUBMITTED, self::TOPIC_DELETED, self::POST_DELETED);

	/** @param ?int $previousPostId after a post is deleted, the post before it; null when there is none */
	public function __construct(private readonly string $step, private readonly DeletablePostInterface $post, private readonly ?int $previousPostId = null) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('A deletion has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function post(): DeletablePostInterface {
		return $this->post;
	}

	public function previousPostId(): ?int {
		return $this->previousPostId;
	}
}
