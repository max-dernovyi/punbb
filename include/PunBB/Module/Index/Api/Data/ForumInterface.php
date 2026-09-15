<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Api\Data;

/**
 * A forum as the board index lists it, with the category it is in.
 */
interface ForumInterface {
	public function categoryId(): int;

	public function categoryName(): string;

	public function id(): int;

	public function name(): string;

	/** The administrator's markup; '' when there is none. */
	public function description(): string;

	/** Where a forum on another site leads; '' for a forum of this board. */
	public function redirectUrl(): string;

	/** @return list<ModeratorInterface> */
	public function moderators(): array;

	public function topicCount(): int;

	public function postCount(): int;

	/** When its last post was made; null when it has none. */
	public function lastPost(): ?int;

	public function lastPostId(): ?int;

	public function lastPoster(): ?string;
}
