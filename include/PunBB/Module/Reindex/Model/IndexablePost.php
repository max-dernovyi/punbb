<?php

declare(strict_types=1);

namespace PunBB\Module\Reindex\Model;

use PunBB\Module\Reindex\Api\Data\IndexablePostInterface;

final readonly class IndexablePost implements IndexablePostInterface {
	public function __construct(private int $id, private string $message, private int $topicId, private string $subject, private int $firstPostId) {}

	public function id(): int {
		return $this->id;
	}

	public function message(): string {
		return $this->message;
	}

	public function topicId(): int {
		return $this->topicId;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function isTopic(): bool {
		return $this->id === $this->firstPostId;
	}
}
