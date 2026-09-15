<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Api\Data;

/**
 * A member's name changing everywhere the board wrote it.
 */
interface RenameInterface {
	public function userId(): int;

	public function oldName(): string;

	public function newName(): string;
}
