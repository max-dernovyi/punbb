<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Model;

use PunBB\Module\Moderate\Api\Data\MergeTargetInterface;

final readonly class MergeTarget implements MergeTargetInterface {
	public function __construct(private int $topicCount, private ?int $lowestId) {}

	public function topicCount(): int {
		return $this->topicCount;
	}

	public function lowestId(): ?int {
		return $this->lowestId;
	}
}
