<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Manifest;

use PunBB\Module\Extensions\Api\Data\ManifestInterface;

/**
 * The manifests extensions and hotfixes are installed from.
 */
interface ManifestsInterface {
	/** The manifest of extensions/$id/. */
	public function local(string $id): ManifestReading;

	/** The manifest of hotfix $id, fetched from the hotfix service. */
	public function hotfix(string $id): ManifestReading;

	/** @return list<LocalManifest> every directory under extensions/, in the order the filesystem lists them */
	public function directory(): array;

	/** Whether the forum takes an extension tested up to what $manifest says. */
	public function supports(ManifestInterface $manifest): bool;

	/** Whether the forum is newer than anything $manifest was tested on. */
	public function outgrows(ManifestInterface $manifest): bool;
}
