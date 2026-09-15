<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Model;

use PunBB\Module\Index\Api\Data\ForumInterface;
use PunBB\Module\Index\Api\Data\ModeratorInterface;

final readonly class Forum implements ForumInterface {
	/** @param list<ModeratorInterface> $moderators */
	public function __construct(
		private int $categoryId,
		private string $categoryName,
		private int $id,
		private string $name,
		private string $description,
		private string $redirectUrl,
		private array $moderators,
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

	public function moderators(): array {
		return $this->moderators;
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
