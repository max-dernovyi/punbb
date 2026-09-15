<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Api\Data;

/**
 * The code a manifest attaches at one or more hook points.
 */
interface ManifestHookInterface {
	/** @return list<string> the hook points, trimmed */
	public function points(): array;

	/** The code, trimmed. */
	public function code(): string;

	public function priority(): int;
}
