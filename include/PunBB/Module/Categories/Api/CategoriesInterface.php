<?php

declare(strict_types=1);

namespace PunBB\Module\Categories\Api;

use PunBB\Module\Categories\Api\Data\CategoryInterface;

/**
 * The categories the board's forums are grouped in.
 */
interface CategoriesInterface {
	/** @return list<CategoryInterface> every category, by its position */
	public function all(): array;

	/** @return list<CategoryInterface> every category, by its id */
	public function allById(): array;

	/** The name of category $id; null when there is none. */
	public function name(int $id): ?string;

	/** @return list<int> the forums in category $id */
	public function forumIds(int $id): array;

	/** Stores each of $categories as a new category; their ids are not read. */
	public function add(CategoryInterface ...$categories): void;

	/** Stores each of $categories over the category of its id. */
	public function update(CategoryInterface ...$categories): void;

	/** Removes the forums $forumIds, once their topics are gone. */
	public function removeForums(int ...$forumIds): void;

	/** Removes every subscription to the forums $forumIds. */
	public function removeForumSubscriptions(int ...$forumIds): void;

	/** Removes the categories $ids, once their forums are gone. */
	public function remove(int ...$ids): void;
}
