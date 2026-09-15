<?php

declare(strict_types=1);

namespace PunBB\Module\Reports\Model;

use PunBB\Module\Reports\Api\Data\ReportInterface;

final readonly class Report implements ReportInterface {
	public function __construct(
		private int $id,
		private ?int $postId,
		private int $topicId,
		private ?string $subject,
		private int $forumId,
		private ?string $forumName,
		private int $reporterId,
		private ?string $reporter,
		private int $created,
		private string $message,
		private ?int $zapped = null,
		private ?int $zappedById = null,
		private ?string $zappedBy = null
	) {}

	public function id(): int {
		return $this->id;
	}

	public function postId(): ?int {
		return $this->postId;
	}

	public function topicId(): int {
		return $this->topicId;
	}

	public function subject(): ?string {
		return $this->subject;
	}

	public function forumId(): int {
		return $this->forumId;
	}

	public function forumName(): ?string {
		return $this->forumName;
	}

	public function reporterId(): int {
		return $this->reporterId;
	}

	public function reporter(): ?string {
		return $this->reporter;
	}

	public function created(): int {
		return $this->created;
	}

	public function message(): string {
		return $this->message;
	}

	public function zapped(): ?int {
		return $this->zapped;
	}

	public function zappedById(): ?int {
		return $this->zappedById;
	}

	public function zappedBy(): ?string {
		return $this->zappedBy;
	}
}
