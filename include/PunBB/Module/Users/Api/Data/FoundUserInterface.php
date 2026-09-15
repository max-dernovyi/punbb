<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Api\Data;

/**
 * A user a search found, with what the results show of them.
 */
interface FoundUserInterface {
	public function id(): int;

	public function username(): string;

	public function email(): string;

	/** The user's own title; '' for none. */
	public function title(): string;

	public function postCount(): int;

	/** What the administrators noted about the user; '' for nothing. */
	public function adminNote(): string;

	/** The user's group; null when it is gone. */
	public function groupId(): ?int;

	/** The title the group gives its members; null for none. */
	public function groupTitle(): ?string;
}
