<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Api\Data;

/**
 * When someone last searched, which the search flood interval counts from.
 */
interface SearchMarkInterface {
	/** The member who searched; null for a guest. */
	public function memberId(): ?int;

	/** The address a guest searched from. */
	public function address(): string;

	public function at(): int;
}
