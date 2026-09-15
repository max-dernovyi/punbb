<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Prune;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Prune\Event\PruningStep;

/**
 * Runs the point at each step of a confirmed prune, with what it prunes in the
 * variables admin/prune.php held it in.
 */
final class PruningStepObserver {
	public const POINTS = array(
		PruningStep::SUBMITTED	=> 'apr_prune_comply_form_submitted',
		PruningStep::PRUNED		=> 'apr_prune_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(PruningStep $event): void {
		$GLOBALS['prune_from'] = $event->from();
		$GLOBALS['prune_days'] = $event->days();
		$GLOBALS['prune_sticky'] = $event->sticky() ? 1 : 0;
		$GLOBALS['prune_date'] = $event->lastPostBefore() ?? -1;

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
