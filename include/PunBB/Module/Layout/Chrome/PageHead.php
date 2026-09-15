<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Chrome;

use PunBB\Module\Layout\View\Html;

/**
 * What a page tells its chrome before the chrome is built: who it is, where it
 * sits, and what the header shows about it.
 */
final readonly class PageHead {
	/**
	 * @param string $id the page's id: 'userlist', 'help', 'admin-information'
	 * @param list<Crumb> $crumbs
	 * @param ?string $section the administration section it belongs to
	 * @param bool $indexable whether search engines may index it
	 * @param ?int $page the number of the page of a listing, shown in the title
	 * @param ?Html $pageCount the page count beside the main heading
	 * @param array<string, Html> $pagePost what the header and the footer of the content carry, by name: 'paging'
	 * @param array<string, Html> $navigation the head's first, previous, next and last links, by name
	 * @param ?Html $mainTitle the page's main heading; null for the last breadcrumb
	 * @param ?string $view which of its views the page shows, where one id has several: 'confirm'
	 * @param list<PageScript> $scripts the page's own scripts, registered before the header is built
	 * @param array<string, Html> $menu the entries of the menu of the page's sections, by name
	 */
	public function __construct(
		public string $id,
		public array $crumbs,
		public ?string $section = null,
		public bool $indexable = false,
		public ?int $page = null,
		public ?Html $pageCount = null,
		public array $pagePost = array(),
		public array $navigation = array(),
		public ?Html $mainTitle = null,
		public ?string $view = null,
		public array $scripts = array(),
		public array $menu = array()
	) {}
}
