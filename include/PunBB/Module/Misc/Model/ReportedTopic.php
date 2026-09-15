<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Model;

use PunBB\Module\Misc\Api\Data\ReportedTopicInterface;

final readonly class ReportedTopic implements ReportedTopicInterface {
	public function __construct(private int $id, private string $subject, private int $forumId) {}

	public function id(): int {
		return $this->id;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function forumId(): int {
		return $this->forumId;
	}
}
