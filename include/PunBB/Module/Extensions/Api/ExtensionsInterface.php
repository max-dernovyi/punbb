<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Api;

use PunBB\Module\Extensions\Api\Data\ExtensionRecordInterface;
use PunBB\Module\Extensions\Api\Data\ExtensionStatusInterface;
use PunBB\Module\Extensions\Api\Data\ExtensionVersionInterface;
use PunBB\Module\Extensions\Api\Data\HookRecordInterface;
use PunBB\Module\Extensions\Api\Data\InstalledExtensionInterface;

/**
 * The installed extensions and hotfixes, and the code they attach at hook points.
 */
interface ExtensionsInterface {
	/** @return list<InstalledExtensionInterface> by title */
	public function all(): array;

	/** @return list<ExtensionVersionInterface> the enabled extensions, which an installation's dependencies are checked against */
	public function enabledVersions(): array;

	/** The version extension $id is installed at; null when it is not installed. */
	public function installedVersion(string $id): ?string;

	/** Stores what an upgrade changes about each installed extension. */
	public function update(ExtensionRecordInterface ...$extensions): void;

	/** Removes the hooks of each extension an upgrade installs again. */
	public function clearHooks(string ...$extensionIds): void;

	public function add(ExtensionRecordInterface ...$extensions): void;

	public function addHooks(HookRecordInterface ...$hooks): void;

	public function find(string $id): ?InstalledExtensionInterface;

	/** The first installed extension that depends on extension $id; null when none does. */
	public function dependent(string $id): ?string;

	/** Removes the hooks of each extension being uninstalled. */
	public function removeHooks(string ...$extensionIds): void;

	public function remove(string ...$ids): void;

	/** Whether extension $id is disabled; null when it is not installed. */
	public function isDisabled(string $id): ?bool;

	/** The first enabled extension that depends on extension $id; null when none does. */
	public function enabledDependent(string $id): ?string;

	/** @return list<string> the ids extension $id depends on */
	public function dependencies(string $id): array;

	/** @return list<string> the ids of the enabled extensions */
	public function enabledIds(): array;

	public function setDisabled(ExtensionStatusInterface ...$statuses): void;
}
