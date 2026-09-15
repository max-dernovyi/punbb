<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Api\Data;

/**
 * An extension a manifest says must be installed and enabled first.
 */
interface ManifestDependencyInterface {
	public function id(): string;

	/** The oldest version that will do; '' for any. */
	public function minVersion(): string;
}
