<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Api\Data;

/**
 * A visitor online: a member by their username, or a guest by their address.
 */
interface OnlineVisitorInterface {
	public function userId(): int;

	public function ident(): string;

	public function isGuest(): bool;
}
