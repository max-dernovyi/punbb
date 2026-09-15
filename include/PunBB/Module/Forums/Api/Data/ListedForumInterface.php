<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Api\Data;

/**
 * A forum in the list of the forums page, with the category it is listed under.
 */
interface ListedForumInterface {
	public function categoryId(): int;

	public function categoryName(): string;

	public function id(): int;

	public function name(): string;

	public function position(): int;
}
