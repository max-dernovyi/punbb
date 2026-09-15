<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum\Model;

use PunBB\Module\Viewforum\Api\Data\ModeratorInterface;
use PunBB\Module\Viewforum\Api\Data\ViewedForumInterface;

final readonly class ViewedForum implements ViewedForumInterface {
	/** @param list<ModeratorInterface> $moderators */
	public function __construct(
		private int $id,
		private string $name,
		private string $description,
		private string $redirectUrl,
		private array $moderators,
		private int $topicCount,
		private bool $sortsByPosted,
		private ?bool $groupPostsTopics,
		private bool $subscribed
	) {}

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

	public function sortsByPosted(): bool {
		return $this->sortsByPosted;
	}

	public function groupPostsTopics(): ?bool {
		return $this->groupPostsTopics;
	}

	public function isSubscribed(): bool {
		return $this->subscribed;
	}
}
