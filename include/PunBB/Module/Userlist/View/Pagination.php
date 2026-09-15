<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\View;

/**
 * Where a page of a listing starts: the page asked for when it exists, the
 * first one otherwise.
 */
final readonly class Pagination {
	/**
	 * @param int $lastOnPage the number the listing's heading gives the last item shown, as the forum has always counted it
	 */
	private function __construct(
		public int $pages,
		public int $page,
		public int $offset,
		public int $lastOnPage
	) {}

	/** @param mixed $requested the page number as the request carries it */
	public static function of(int $items, mixed $requested, int $perPage): self {
		$pages = (int) ceil($items / $perPage);
		$page = is_numeric($requested) && $requested > 1 && $requested <= $pages ? (int) $requested : 1;

		return new self($pages, $page, $perPage * ($page - 1), min($perPage, $items));
	}
}
