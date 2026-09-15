<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Api;

use PunBB\Module\Update\Api\Data\SettingInterface;

/**
 * The board's configuration as the update finds and leaves it.
 */
interface BoardSettingsInterface {
	/** The version the board's database was last installed or updated to; null when it names none. */
	public function version(): ?string;

	/** @return list<SettingInterface> every option */
	public function all(): array;

	public function add(SettingInterface ...$settings): void;

	/** Stores each option's value under its name. */
	public function update(SettingInterface ...$settings): void;

	/** Stores $setting only where the option holds $expected. */
	public function replace(SettingInterface $setting, string $expected): void;

	public function rename(string $from, string $to): void;

	public function remove(string ...$names): void;
}
