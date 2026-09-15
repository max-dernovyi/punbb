<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Model;

use PunBB\Module\Search\Api\Data\ListingInterface;

final readonly class Listing implements ListingInterface {
	public const POSTS = 'posts';

	public const TOPICS = 'topics';

	public const FORUMS = 'forums';

	public function __construct(
		private ?string $action,
		private string $showAs,
		private string $url,
		private int|string $argument
	) {}

	public function action(): ?string {
		return $this->action;
	}

	public function showAs(): string {
		return $this->showAs;
	}

	public function url(): string {
		return $this->url;
	}

	public function argument(): int|string {
		return $this->argument;
	}

	/**
	 * The address's arguments, none where search.php linked without one.
	 *
	 * @return list<int|string>
	 */
	public static function arguments(ListingInterface $listing): array {
		return $listing->argument() === '' || $listing->argument() === 0 ? array() : array($listing->argument());
	}
}
