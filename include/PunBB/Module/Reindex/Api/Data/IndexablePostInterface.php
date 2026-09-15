<?php

declare(strict_types=1);

namespace PunBB\Module\Reindex\Api\Data;

/**
 * A post as the search index takes it: its text, and its topic's subject when it opens the topic.
 */
interface IndexablePostInterface {
	public function id(): int;

	public function message(): string;

	public function topicId(): int;

	public function subject(): string;

	/** Whether it opens its topic, so its subject is indexed with it. */
	public function isTopic(): bool;
}
