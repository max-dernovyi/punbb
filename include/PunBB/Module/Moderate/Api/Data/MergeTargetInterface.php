<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Api\Data;

/**
 * The topics about to be merged as the forum holds them: how many of them it
 * holds, and the oldest, which the others are merged into.
 */
interface MergeTargetInterface {
	public function topicCount(): int;

	/** The lowest id among them; null when the forum holds none of them. */
	public function lowestId(): ?int;
}
