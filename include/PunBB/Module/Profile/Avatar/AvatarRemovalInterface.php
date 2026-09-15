<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Avatar;

/**
 * Takes a member's avatar off the board: its file, whatever image type it is,
 * and what their account records of it. The module declares it, the
 * bootstrap's side wires it.
 */
interface AvatarRemovalInterface {
	public function remove(int $userId): void;
}
