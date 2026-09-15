<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\Api\Data;

/**
 * A member as the member list shows them.
 */
interface MemberInterface {
	public function id(): int;

	public function username(): string;

	/** Their own title; '' when they have none. */
	public function title(): string;

	public function postCount(): int;

	public function registered(): int;

	/** Their group; null when the group no longer exists. */
	public function groupId(): ?int;

	/** The title their group gives; null when it gives none or no longer exists. */
	public function groupTitle(): ?string;
}
