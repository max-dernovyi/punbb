<?php

declare(strict_types=1);

namespace PunBB\Module\Settings\Api\Data;

/**
 * A setting as the config table stores it.
 */
interface SettingInterface {
	/** The stored name: 'o_board_title', 'p_allow_dupe_email'. */
	public function name(): string;

	/** The stored value; null for NULL. */
	public function value(): ?string;
}
