<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Model;

use PunBB\Module\Extern\Api\Data\FeedItemInterface;

final readonly class FeedItem implements FeedItemInterface {
	public function __construct(
		private int $id,
		private string $title,
		private string $link,
		private string $description,
		private string $authorName,
		private ?string $authorEmail,
		private ?string $authorUri,
		private int $published
	) {}

	public function id(): int {
		return $this->id;
	}

	public function title(): string {
		return $this->title;
	}

	public function link(): string {
		return $this->link;
	}

	public function description(): string {
		return $this->description;
	}

	public function authorName(): string {
		return $this->authorName;
	}

	public function authorEmail(): ?string {
		return $this->authorEmail;
	}

	public function authorUri(): ?string {
		return $this->authorUri;
	}

	public function published(): int {
		return $this->published;
	}
}
