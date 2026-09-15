<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Model;

use PunBB\Module\Post\Api\Data\QuoteInterface;

final readonly class Quote implements QuoteInterface {
	public function __construct(private string $poster, private string $message) {}

	public function poster(): string {
		return $this->poster;
	}

	public function message(): string {
		return $this->message;
	}
}
