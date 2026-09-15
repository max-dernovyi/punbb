<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Setup;

use PunBB\Module\Install\Api\Data\ExtensionInterface;
use PunBB\Module\Install\Manifest\BundledExtensionsInterface;
use PunBB\Module\Install\Model\BundledExtension;
use PunBB\Module\Install\Model\BundledHook;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;

/**
 * extensions/pun_repository/manifest.xml, read by xml_to_array() of include/xml.php.
 */
final class LegacyBundledExtensions implements BundledExtensionsInterface {
	private const REPOSITORY = 'pun_repository';

	public function hasRepository(): bool {
		return file_exists(self::manifest());
	}

	public function repository(int $installed): ?ExtensionInterface {
		if (!is_readable(self::manifest()))
			return null;

		if (!function_exists('xml_to_array'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/xml.php');

		$data = \xml_to_array((string) file_get_contents(self::manifest()));
		$extension = is_array($data) && is_array($data['extension'] ?? null) ? $data['extension'] : null;
		if ($extension === null)
			return null;

		$hooks = array();
		$listed = is_array($extension['hooks'] ?? null) && is_array($extension['hooks']['hook'] ?? null) ? $extension['hooks']['hook'] : array();
		foreach ($listed as $hook)
		{
			if (!is_array($hook))
				continue;

			$attributes = is_array($hook['attributes'] ?? null) ? $hook['attributes'] : array();
			foreach (explode(',', Markers::markup($attributes['id'] ?? '')) as $point)
				$hooks[] = new BundledHook(trim($point), trim(Markers::markup($hook['content'] ?? '')), (int) Markers::markup($attributes['priority'] ?? 5), $installed);
		}

		return new BundledExtension(self::REPOSITORY, Markers::markup($extension['title'] ?? ''), Markers::markup($extension['version'] ?? ''), Markers::markup($extension['description'] ?? ''), Markers::markup($extension['author'] ?? ''), $hooks);
	}

	private static function manifest(): string {
		return LegacyChromeSource::root().'extensions/'.self::REPOSITORY.'/manifest.xml';
	}
}
