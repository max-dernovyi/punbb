<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Api\Data;

/**
 * A forum a member subscribes to, with its category.
 */
interface ResultForumInterface {
	public function categoryId(): int;

	public function categoryName(): string;

	public function id(): int;

	public function name(): string;

	/** '' for none. */
	public function description(): string;

	/** '' for a forum of this board. */
	public function redirectUrl(): string;

	public function topicCount(): int;

	public function postCount(): int;

	/** When it was last posted in; null when it never was. */
	public function lastPost(): ?int;

	public function lastPostId(): ?int;

	public function lastPoster(): ?string;
}
