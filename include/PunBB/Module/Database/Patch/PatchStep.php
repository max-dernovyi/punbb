<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Patch;

/**
 * What one batch of a data patch did, and where the next batch starts.
 */
final readonly class PatchStep {
	/**
	 * @param list<string> $lines what the batch did, as plain text
	 * @param ?int $next where the next batch starts; null when the patch is complete
	 */
	public function __construct(public array $lines = array(), public ?int $next = null) {}
}
