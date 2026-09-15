<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Api\Data;

/**
 * What a manifest tells the administrator before installing or uninstalling.
 */
interface ManifestNoteInterface {
	/** install or uninstall. */
	public function type(): string;

	public function content(): string;
}
