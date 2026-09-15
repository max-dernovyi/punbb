<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Api\Data;

/**
 * A redirect left in a forum a topic was moved from, pointing at the topic.
 */
interface RedirectTopicInterface {
	public function poster(): string;

	public function subject(): string;

	public function posted(): int;

	public function lastPost(): int;

	/** The topic it points at. */
	public function movedTo(): int;

	/** The forum it is left in. */
	public function forumId(): int;
}
