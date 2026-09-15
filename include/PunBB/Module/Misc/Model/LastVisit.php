<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Model;

use PunBB\Module\Misc\Api\Data\LastVisitInterface;

final readonly class LastVisit implements LastVisitInterface {
	public function __construct(private int $userId, private int $at) {}

	public function userId(): int {
		return $this->userId;
	}

	public function at(): int {
		return $this->at;
	}
}
