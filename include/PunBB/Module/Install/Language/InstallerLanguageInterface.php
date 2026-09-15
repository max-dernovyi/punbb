<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Language;

use PunBB\Module\Layout\View\Html;

/**
 * The language packs, as the installer speaks them before a board has a language.
 */
interface InstallerLanguageInterface {
	/** @return list<string> the packs a board can use */
	public function packs(): array;

	/** Whether pack $language carries the installer's strings. */
	public function speaksInstaller(string $language): bool;

	/**
	 * The strings of $file ('install', 'admin_settings') in pack $language.
	 *
	 * @return array<string, Html>
	 */
	public function strings(string $language, string $file): array;
}
