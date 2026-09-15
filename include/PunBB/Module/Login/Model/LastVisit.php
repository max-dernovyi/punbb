<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Model;

use PunBB\Module\Login\Api\Data\LastVisitInterface;

final readonly class LastVisit implements LastVisitInterface {
	public function __construct(private int $userId, private int $at) {}

	public function userId(): int {
		return $this->userId;
	}

	public function at(): int {
		return $this->at;
	}
}
