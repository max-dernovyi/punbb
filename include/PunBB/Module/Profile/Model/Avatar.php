<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Model;

use PunBB\Module\Profile\Api\Data\AvatarInterface;

final readonly class Avatar implements AvatarInterface {
	public function __construct(private int $userId, private int $type, private int $width, private int $height) {}

	public function userId(): int {
		return $this->userId;
	}

	public function type(): int {
		return $this->type;
	}

	public function width(): int {
		return $this->width;
	}

	public function height(): int {
		return $this->height;
	}
}
