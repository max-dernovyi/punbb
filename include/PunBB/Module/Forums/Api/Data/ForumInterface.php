<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Api\Data;

/**
 * A forum as the forums page edits it; the id is 0 for a forum not stored yet.
 */
interface ForumInterface {
	public function id(): int;

	public function name(): string;

	/** Markup; null when the forum has none. */
	public function description(): ?string;

	/** Where the forum sends its visitors; null when it holds topics. */
	public function redirectUrl(): ?string;

	/** 0 by last post, 1 by topic start, or what an extension stores. */
	public function sortBy(): int;

	public function categoryId(): int;

	public function position(): int;

	public function topicCount(): int;
}
