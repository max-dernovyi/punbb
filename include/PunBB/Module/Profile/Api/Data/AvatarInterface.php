<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Api\Data;

/**
 * The avatar a member uploaded, as their account records it.
 */
interface AvatarInterface {
	public function userId(): int;

	/** The image type: 1 GIF, 2 JPEG, 3 PNG. */
	public function type(): int;

	public function width(): int;

	public function height(): int;
}
