<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Api\Data;

/**
 * The code an extension attaches at one hook point.
 */
interface ExtensionHookInterface {
	/** The hook point. */
	public function point(): string;

	public function code(): string;

	public function priority(): int;

	/** When it was installed, as a Unix timestamp. */
	public function installed(): int;
}
