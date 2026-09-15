<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Api\Data;

/**
 * A visitor online now: a member by their username, or a guest by their address.
 */
interface OnlineVisitorInterface {
	/** The member's id; 1 for a guest. */
	public function userId(): int;

	public function ident(): string;

	public function isGuest(): bool;
}
