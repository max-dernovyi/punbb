<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Api\Data\InstalledExtensionInterface;
use PunBB\Module\Extensions\Api\Data\ManifestInterface;
use PunBB\Module\Extensions\Model\InstalledExtension;
use PunBB\Module\LegacyBridge\Page\KeptRows;

/**
 * The extensions and manifests as admin/extensions.php handed them to extension
 * code: a manifest as xml_to_array() parsed it, an extension as its row, with
 * any column a query point added.
 */
final class ExtensionRows {
	public function __construct(private readonly KeptRows $rows) {}

	/** @param array<array-key, mixed> $row */
	public function keep(object $answer, array $row): void {
		$this->rows->keep($answer, $row);
	}

	/** @return array<array-key, mixed> the manifest as it was parsed, or one built from it */
	public function manifest(ManifestInterface $manifest): array {
		return $this->rows->row($manifest) ?? array('extension' => array(
			'id'			=> $manifest->id(),
			'title'			=> $manifest->title(),
			'version'		=> $manifest->version(),
			'description'	=> $manifest->description(),
			'author'		=> $manifest->author(),
			'maxtestedon'	=> $manifest->maxTestedOn(),
			'dependencies'	=> array_map(static fn ($dependency): string => $dependency->id(), $manifest->dependencies()),
			'note'			=> array_map(static fn ($note): array => array('content' => $note->content(), 'attributes' => array('type' => $note->type())), $manifest->notes()),
			'install'		=> $manifest->installCode(),
			'uninstall'		=> $manifest->uninstallCode(),
		));
	}

	/** @return array<array-key, mixed> the extension's row, or one built from it */
	public function extension(InstalledExtensionInterface $extension): array {
		return $this->rows->row($extension) ?? array(
			'id'				=> $extension->id(),
			'title'				=> $extension->title(),
			'version'			=> $extension->version(),
			'description'		=> $extension->description(),
			'author'			=> $extension->author(),
			'uninstall'			=> $extension->uninstallCode() !== '' ? $extension->uninstallCode() : null,
			'uninstall_note'	=> $extension->uninstallNote() !== '' ? $extension->uninstallNote() : null,
			'disabled'			=> $extension->isDisabled() ? '1' : '0',
			'dependencies'		=> InstalledExtension::dependencyColumn($extension->dependencies()),
		);
	}
}
