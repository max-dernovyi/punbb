<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Chrome;

use PunBB\Module\Layout\View\Html;

/**
 * A page's chrome with its header built, waiting for the page's own content.
 */
interface ChromeInterface {
	/**
	 * The page: its content placed into the chrome with the regions below it.
	 *
	 * @param array<string, Html> $content the page's own regions: main, info, qpost
	 */
	public function close(array $content): string;

	/**
	 * The administrator's alerts the header raised, as observers left them; none for anyone else.
	 *
	 * @return array<string, Html>
	 */
	public function alerts(): array;

	/** Registers $code as an inline script of the page's own, placed after the board's. */
	public function inlineScript(string $code): void;
}
