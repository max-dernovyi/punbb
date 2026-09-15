<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Installation;

use PunBB\Module\Extensions\Api\Data\ManifestInterface;

/**
 * Runs the code an extension installs and uninstalls itself with.
 */
interface ExtensionCodeInterface {
	/**
	 * Runs $manifest's install code for extension $id.
	 *
	 * @param ?string $installedVersion the version an upgrade replaces; null for a fresh install
	 * @return list<string> what the code asks the administrator to read, each markup
	 */
	public function install(string $id, ManifestInterface $manifest, ?string $installedVersion): array;

	/**
	 * Runs extension $id's uninstall code.
	 *
	 * @return list<string> what the code asks the administrator to read, each markup
	 */
	public function uninstall(string $id, string $code): array;
}
