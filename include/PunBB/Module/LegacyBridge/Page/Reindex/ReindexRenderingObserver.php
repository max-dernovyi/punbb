<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Reindex;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Reindex\Event\ReindexRendering;

/**
 * Renders the rebuild form's markup points at their positions, with the form's
 * counts in $forum_page, read back for the fields that follow.
 */
final class ReindexRenderingObserver {
	public const POINTS = array(
		ReindexRendering::MAIN_OUTPUT_START			=> 'ari_main_output_start',
		ReindexRendering::PRE_REBUILD_FIELDSET		=> 'ari_pre_rebuild_fieldset',
		ReindexRendering::PRE_REBUILD_PER_PAGE		=> 'ari_pre_rebuild_per_page',
		ReindexRendering::PRE_REBUILD_START_POST	=> 'ari_pre_rebuild_start_post',
		ReindexRendering::PRE_REBUILD_EMPTY_INDEX	=> 'ari_pre_rebuild_empty_index',
		ReindexRendering::PRE_REBUILD_FIELDSET_END	=> 'ari_pre_rebuild_fieldset_end',
		ReindexRendering::REBUILD_FIELDSET_END		=> 'ari_rebuild_fieldset_end',
		ReindexRendering::END						=> 'ari_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ReindexRendering $event): void {
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
