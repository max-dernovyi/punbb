<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Hook;

use Deprecated;

/**
 * Stands in for `$return = ($hook = get_hook('<id>')) ? eval($hook) : null;` at a legacy
 * statement point; PointEvaluator says what the stored code sees and what reaches the caller.
 */
final class StatementHookRunner {
	public function __construct(private readonly PointEvaluator $points) {}

	/**
	 * @param array<mixed> $exposed variable name => reference to the caller's variable
	 * @return mixed what the stored code returned, null when it did not return
	 */
	#[Deprecated(since: '2.0', message: 'use the event or the plugged contract method that replaces the point')]
	public function run(string $point, array $exposed): mixed {
		return $this->points->run($point, $exposed);
	}
}
