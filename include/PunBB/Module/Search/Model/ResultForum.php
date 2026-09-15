<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Model;

use PunBB\Module\Search\Api\Data\ResultForumInterface;

final readonly class ResultForum implements ResultForumInterface {
	public function __construct(
		private int $categoryId,
		private string $categoryName,
		private int $id,
		private string $name,
		private string $description,
		private string $redirectUrl,
		private int $topicCount,
		private int $postCount,
		private ?int $lastPost,
		private ?int $lastPostId,
		private ?string $lastPoster
	) {}

	public function categoryId(): int {
		return $this->categoryId;
	}

	public function categoryName(): string {
		return $this->categoryName;
	}

	public function id(): int {
		return $this->id;
	}

	public function name(): string {
		return $this->name;
	}

	public function description(): string {
		return $this->description;
	}

	public function redirectUrl(): string {
		return $this->redirectUrl;
	}

	public function topicCount(): int {
		return $this->topicCount;
	}

	public function postCount(): int {
		return $this->postCount;
	}

	public function lastPost(): ?int {
		return $this->lastPost;
	}

	public function lastPostId(): ?int {
		return $this->lastPostId;
	}

	public function lastPoster(): ?string {
		return $this->lastPoster;
	}
}
