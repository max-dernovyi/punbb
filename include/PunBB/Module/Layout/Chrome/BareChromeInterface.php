<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Chrome;

use PunBB\Module\Layout\View\Html;

/**
 * A page shown outside the board's chrome, the redirect or the maintenance
 * message: no header and no footer, only the head the page assembles and its
 * own content.
 */
interface BareChromeInterface {
	/** @return list<Html> the lines the theme's stylesheet script wrote into the head */
	public function themeHead(): array;

	/** The stylesheets registered for the page, the theme's among them once themeHead() ran. */
	public function stylesheets(): Html;

	/**
	 * The page: $regions placed into the chrome, with the language it is in.
	 *
	 * @param array<string, Html> $regions the page's own regions: head, main
	 */
	public function close(array $regions): string;
}
