<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Config;

/**
 * The style, language and URL scheme packs installed with the board, by the
 * name of their directory, in the order the board finds them.
 */
interface PacksInterface {
	/** @return list<string> every directory of style/ holding the style's script */
	public function styles(): array;

	/** @return list<string> every directory of lang/ holding the pack's common strings */
	public function languages(): array;

	/** @return list<string> every directory of include/url/ holding a scheme */
	public function urlSchemes(): array;
}
