<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Api\Data;

/**
 * A forum being moderated, as the visitor's group may read it.
 */
interface ModeratedForumInterface {
	public function id(): int;

	public function name(): string;

	/** Where a forum on another site sends its visitors; '' for a forum of this board. */
	public function redirectUrl(): string;

	public function topicCount(): int;

	/** @return list<ModeratorInterface> */
	public function moderators(): array;

	/** Whether its topics are listed by when they were started, not by their last post. */
	public function sortsByPosted(): bool;
}
