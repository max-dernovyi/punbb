<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Api\Data;

/**
 * A forum the search form offers to search in, with its category.
 */
interface SearchableForumInterface {
	public function categoryId(): int;

	public function categoryName(): string;

	public function id(): int;

	public function name(): string;
}
