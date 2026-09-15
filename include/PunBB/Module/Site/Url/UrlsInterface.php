<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Url;

use PunBB\Module\Layout\View\Html;

/**
 * The forum's addresses in its URL scheme, by name: 'index', 'users_browse'.
 * A URL comes back encoded for an attribute, as the scheme writes it.
 */
interface UrlsInterface {
	/** Where the forum is served from, without a trailing slash. */
	public function base(): string;

	/** @param list<int|string> $arguments filling the scheme's $1, $2, … in turn */
	public function link(string $name, array $arguments = array()): Html;

	/** Whether the scheme, with what extensions added to it, has an address named $name. */
	public function has(string $name): bool;

	/**
	 * $name with the part $sub adds, such as a page number, filled with $subArgument.
	 *
	 * @param list<int|string> $arguments
	 */
	public function sublink(string $name, string $sub, int $subArgument, array $arguments = array()): Html;

	/**
	 * The numbered links to the pages of a listing at $name, $current among them;
	 * a $current of -1 links every page, as a topic's row in a forum does.
	 *
	 * @param list<int|string> $arguments
	 * @param ?Html $separator between the links; null for the language pack's paging separator
	 * @param bool $pageInQuery whether the page number is a query parameter whatever the scheme, as on the administration's lists
	 * @param string $query markup following the address, before the page number: a search's criteria, '?find_user=&amp;order_by=username'
	 */
	public function pagination(int $pages, int $current, string $name, array $arguments = array(), ?Html $separator = null, bool $pageInQuery = false, string $query = ''): Html;

	/** $url, a website a member gave, as a link to it takes it. */
	public function webAddress(string $url): WebAddress;

	/** $text as a pretty URL carries it in a path, such as a forum's name. */
	public function slug(string $text): string;

	/** The URL the visitor requested, on the forum's own origin: what a form on the page posts back to. */
	public function current(): string;
}
