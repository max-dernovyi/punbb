<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Updates;

use PunBB\Module\Extensions\Api\Data\InstalledExtensionInterface;

/**
 * What the update services offer: hotfixes, and newer versions of the installed extensions.
 */
interface UpdatesInterface {
	/** @return list<AvailableHotfix> */
	public function hotfixes(): array;

	/**
	 * @param list<InstalledExtensionInterface> $installed
	 * @return list<LatestVersion> the newest version of each installed extension a repository knows
	 */
	public function latestVersions(array $installed): array;
}
