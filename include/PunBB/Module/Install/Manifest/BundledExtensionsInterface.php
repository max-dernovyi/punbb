<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Manifest;

use PunBB\Module\Install\Api\Data\ExtensionInterface;

/**
 * The extension an installation can install along with the board: pun_repository,
 * the one-click extension downloader, when the forum ships it.
 */
interface BundledExtensionsInterface {
	/** Whether the forum ships the repository extension. */
	public function hasRepository(): bool;

	/** The repository extension as its manifest describes it, installed at $installed; null when its manifest reads as nothing. */
	public function repository(int $installed): ?ExtensionInterface;
}
