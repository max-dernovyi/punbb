<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Config;

/**
 * The board's settings, by their stored name: 'o_board_title', 'p_allow_dupe_email'.
 */
interface SettingsInterface {
	/** The stored value; '' for a setting the board does not have. */
	public function value(string $name): string;

	/** Whether the setting is on: stored as '1'. */
	public function enabled(string $name): bool;

	/** @return array<string, string> every setting the board has, by its stored name */
	public function all(): array;
}
