<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Chrome;

/**
 * Opens a page's chrome: everything above the page's content is built, in
 * order, when the page asks for it.
 */
interface ChromeFactoryInterface {
	public function open(PageHead $head): ChromeInterface;

	/** Opens a page shown outside the board's chrome: Layout::REDIRECT or Layout::MAINTENANCE. */
	public function bare(string $chrome): BareChromeInterface;
}
