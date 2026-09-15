<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Model;

use InvalidArgumentException;
use PunBB\Module\Users\Api\Data\SearchFieldInterface;

final readonly class SearchField implements SearchFieldInterface {
	public function __construct(private string $field, private string $text) {
		if (!in_array($field, self::FIELDS, true))
			throw new InvalidArgumentException(sprintf('Users cannot be searched by "%s"', $field));
	}

	public function field(): string {
		return $this->field;
	}

	public function text(): string {
		return $this->text;
	}
}
