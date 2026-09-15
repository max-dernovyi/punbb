<?php

declare(strict_types=1);

namespace PunBB\Module\Search\View;

/**
 * Which page of the results is shown: the page asked for when it exists, the
 * first otherwise. A listing of forums is one page.
 */
final readonly class Paging {
	private function __construct(
		public int $pages,
		public int $page,
		public int $offset,
		public int $last,
		public int $perPage
	) {}

	/**
	 * @param int $perPage 0 for every result on one page
	 * @param mixed $requested the page number as the request carries it
	 */
	public static function of(int $items, mixed $requested, int $perPage): self {
		$pages = $perPage === 0 ? 1 : (int) ceil($items / $perPage);
		$page = is_numeric($requested) && $requested > 1 && $requested <= $pages ? (int) $requested : 1;
		$offset = $perPage * ($page - 1);

		return new self($pages, $page, $offset, $perPage === 0 ? $items : min($offset + $perPage, $items), $perPage);
	}

	/**
	 * The results on the page.
	 *
	 * @template T
	 * @param list<T> $results
	 * @return list<T>
	 */
	public function slice(array $results): array {
		return array_slice($results, $this->offset, $this->last - $this->offset);
	}
}
