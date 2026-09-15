<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum\View;

/**
 * Which page of a forum's topics is shown: the page asked for when it exists,
 * the first one otherwise.
 */
final readonly class Paging {
	private function __construct(
		public int $pages,
		public int $page,
		public int $offset,
		public int $last
	) {}

	/** @param mixed $requested the page number as the request carries it */
	public static function of(int $items, mixed $requested, int $perPage): self {
		$pages = $perPage > 0 ? (int) ceil($items / $perPage) : 0;
		$page = is_numeric($requested) && $requested > 1 && $requested <= $pages ? (int) $requested : 1;
		$offset = $perPage * ($page - 1);

		return new self($pages, $page, $offset, min($offset + $perPage, $items));
	}
}
