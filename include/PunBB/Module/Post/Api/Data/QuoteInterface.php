<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Api\Data;

/**
 * The post a reply quotes.
 */
interface QuoteInterface {
	public function poster(): string;

	public function message(): string;
}
