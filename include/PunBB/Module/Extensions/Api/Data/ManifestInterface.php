<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Api\Data;

/**
 * An extension's manifest.xml, once it checked out.
 */
interface ManifestInterface {
	public function id(): string;

	public function title(): string;

	public function version(): string;

	public function description(): string;

	public function author(): string;

	/** The newest forum version the extension was tested on. */
	public function maxTestedOn(): string;

	/** @return list<ManifestDependencyInterface> */
	public function dependencies(): array;

	/** @return list<ManifestNoteInterface> */
	public function notes(): array;

	/** The code installing the extension, trimmed; '' when there is none. */
	public function installCode(): string;

	/** The code uninstalling it, trimmed; '' when there is none. */
	public function uninstallCode(): string;

	/** @return list<ManifestHookInterface> */
	public function hooks(): array;
}
