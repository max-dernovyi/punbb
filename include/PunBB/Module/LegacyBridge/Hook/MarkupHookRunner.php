<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Hook;

use Deprecated;
use Throwable;

/**
 * Runs the code stored at a legacy markup point, as `<?php ($hook = get_hook('<id>')) ? eval($hook) : null; ?>`
 * does inside a template, and hands back what it emitted — echoes and the HTML
 * after a `?>` alike — for the template to place where that site stood.
 */
final class MarkupHookRunner {
	public function __construct(private readonly PointEvaluator $points) {}

	/**
	 * A markup site discards what a hook returns; a return only ends the point.
	 *
	 * @param array<mixed> $exposed variable name => reference to the template's variable
	 */
	#[Deprecated(since: '2.0', message: 'use the event that replaces the point, rendered by the layout')]
	public function render(string $point, array $exposed): string {
		ob_start();

		try {
			$this->points->run($point, $exposed);
		}
		catch (Throwable $e) {
			// What was emitted before the throw stays where the legacy site would have left it.
			ob_end_flush();
			throw $e;
		}

		return (string) ob_get_clean();
	}
}
