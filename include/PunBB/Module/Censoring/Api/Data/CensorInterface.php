<?php

declare(strict_types=1);

namespace PunBB\Module\Censoring\Api\Data;

/**
 * A censored word and what replaces it; the id is 0 for a word not stored yet.
 */
interface CensorInterface {
	public function id(): int;

	public function searchFor(): string;

	public function replaceWith(): string;
}
