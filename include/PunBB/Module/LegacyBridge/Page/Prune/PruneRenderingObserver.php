<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Prune;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Prune\Event\PruneRendering;

/**
 * Renders the pruning page's markup points at their positions, with the form's
 * counts in $forum_page, read back for the fields that follow.
 */
final class PruneRenderingObserver {
	public const POINTS = array(
		PruneRendering::MAIN_OUTPUT_START		=> 'apr_main_output_start',
		PruneRendering::PRE_PRUNE_FIELDSET		=> 'apr_pre_prune_fieldset',
		PruneRendering::PRE_PRUNE_FROM			=> 'apr_pre_prune_from',
		PruneRendering::PRE_PRUNE_DAYS			=> 'apr_pre_prune_days',
		PruneRendering::PRE_PRUNE_STICKY		=> 'apr_pre_prune_sticky',
		PruneRendering::PRE_PRUNE_FIELDSET_END	=> 'apr_pre_prune_fieldset_end',
		PruneRendering::PRUNE_FIELDSET_END		=> 'apr_prune_fieldset_end',
		PruneRendering::END						=> 'apr_end',
		PruneRendering::COMPLY_OUTPUT_START		=> 'apr_prune_comply_output_start',
		PruneRendering::COMPLY_PRE_BUTTONS		=> 'apr_prune_comply_pre_buttons',
		PruneRendering::COMPLY_END				=> 'apr_prune_comply_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(PruneRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page['group_count'] = $event->groupCount();
		$page['item_count'] = $event->itemCount();
		$page['fld_count'] = $event->fieldCount();
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved($point, $event));

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$event->count((int) Markers::markup($page['group_count'] ?? 0), (int) Markers::markup($page['item_count'] ?? 0), (int) Markers::markup($page['fld_count'] ?? 0));
	}
}
