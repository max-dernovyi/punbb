<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Site\Config\PacksInterface;

/**
 * get_style_packs(), get_language_packs() and get_scheme_packs() of
 * include/functions.php, with the extension code attached to them.
 */
final class LegacyPacks implements PacksInterface {
	public function styles(): array {
		return self::names(\get_style_packs());
	}

	public function languages(): array {
		return self::names(\get_language_packs());
	}

	public function urlSchemes(): array {
		return self::names(\get_scheme_packs());
	}

	/** @return list<string> */
	private static function names(mixed $packs): array {
		return array_values(array_map(static fn (mixed $pack): string => Markers::markup($pack), is_array($packs) ? $packs : array()));
	}
}
