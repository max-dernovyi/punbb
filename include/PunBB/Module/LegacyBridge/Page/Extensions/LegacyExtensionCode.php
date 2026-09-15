<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Api\Data\ManifestInterface;
use PunBB\Module\Extensions\Installation\ExtensionCodeInterface;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;

/**
 * An extension's install and uninstall code, run as admin/extensions.php ran it:
 * at global scope, with the extension in $ext_info, the version an upgrade
 * replaces in EXT_CUR_VERSION, and what the administrator is to read in $notices.
 */
final class LegacyExtensionCode implements ExtensionCodeInterface {
	public function __construct(private readonly ExtensionRows $rows) {}

	public function install(string $id, ManifestInterface $manifest, ?string $installedVersion): array {
		$base = Markers::markup($GLOBALS['base_url'] ?? '');

		$dependencies = array();
		foreach ($manifest->dependencies() as $dependency)
			$dependencies[$dependency->id()] = array(
				'id'	=> $dependency->id(),
				'path'	=> LegacyChromeSource::root().'extensions/'.$dependency->id(),
				'url'	=> $base.'/extensions/'.$dependency->id(),
			);

		$GLOBALS['ext_info'] = array('id' => $id, 'path' => LegacyChromeSource::root().'extensions/'.$id, 'url' => $base.'/extensions/'.$id, 'dependencies' => $dependencies);
		$GLOBALS['ext_data'] = $this->rows->manifest($manifest);
		$GLOBALS['ext_version'] = $installedVersion;
		$GLOBALS['notices'] = array();

		// The extension's install routine reads it to upgrade what the version it replaces left
		if ($installedVersion !== null && !defined('EXT_CUR_VERSION'))
			define('EXT_CUR_VERSION', $installedVersion);

		if ($manifest->installCode() !== '')
			self::evaluate($manifest->installCode());

		return array_values(Markers::entries($GLOBALS['notices'] ?? null));
	}

	public function uninstall(string $id, string $code): array {
		$GLOBALS['ext_info'] = array('id' => $id, 'path' => LegacyChromeSource::root().'extensions/'.$id, 'url' => Markers::markup($GLOBALS['base_url'] ?? '').'/extensions/'.$id);
		$GLOBALS['notices'] = array();

		if ($code !== '')
			self::evaluate($code);

		return array_values(Markers::entries($GLOBALS['notices'] ?? null));
	}

	/** Runs $code at global scope: what it creates is a global once it is done. */
	private static function evaluate(string $code): void {
		$defined = (static function (string $__code, array $__scope): array {
			extract($__scope, EXTR_REFS | EXTR_SKIP);
			unset($__scope);
			eval($__code);

			return get_defined_vars();
		})($code, LegacyScope::with(array()));

		foreach ($defined as $name => $value)
			if ($name !== '__code' && !array_key_exists($name, $GLOBALS))
				$GLOBALS[$name] = $value;
	}
}
