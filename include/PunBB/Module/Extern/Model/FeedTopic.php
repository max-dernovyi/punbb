<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Model;

use PunBB\Module\Extern\Api\Data\FeedTopicInterface;

final readonly class FeedTopic implements FeedTopicInterface {
	public function __construct(private int $id, private string $subject, private int $firstPostId) {}

	public function id(): int {
		return $this->id;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function firstPostId(): int {
		return $this->firstPostId;
	}
}
