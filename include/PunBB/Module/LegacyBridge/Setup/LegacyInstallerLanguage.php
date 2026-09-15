<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Setup;

use PunBB\Module\Install\Language\InstallerLanguageInterface;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Layout\View\Html;

/**
 * The language packs under lang/, through get_language_packs() of
 * include/functions.php, and their files read for the arrays they define.
 */
final class LegacyInstallerLanguage implements InstallerLanguageInterface {
	public function packs(): array {
		return array_values(array_map(Markers::markup(...), (array) \get_language_packs()));
	}

	public function speaksInstaller(string $language): bool {
		// A name that could leave lang/ names no pack
		if ($language === '' || $language !== preg_replace('#[\.\\\/]#', '', $language))
			return false;

		if (!file_exists(LegacyChromeSource::root().'lang/'.$language.'/install.php'))
			return false;

		return true;
	}

	public function strings(string $language, string $file): array {
		$path = LegacyChromeSource::root().'lang/'.$language.'/'.$file.'.php';
		if (!$this->speaksInstaller($language) || preg_match('/^[a-z_]+$/', $file) !== 1 || !is_file($path))
			return array();

		$strings = (static function (string $__file): mixed {
			require $__file;

			return get_defined_vars()['lang_'.basename($__file, '.php')] ?? null;
		})($path);

		$markup = array();
		foreach (is_array($strings) ? $strings : array() as $key => $string)
			$markup[(string) $key] = new Html(Markers::markup($string));

		return $markup;
	}
}
