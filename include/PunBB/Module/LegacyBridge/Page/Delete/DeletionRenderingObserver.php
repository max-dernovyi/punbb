<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Delete;

use PunBB\Module\Delete\Event\DeletionRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the deletion page's markup points at their positions, with the
 * form's counts in $forum_page, read back for the fields that follow. At the
 * end the post's forum is left in $forum_id, where the jump list below the
 * page preselects it.
 */
final class DeletionRenderingObserver {
	public const POINTS = array(
		DeletionRendering::MAIN_OUTPUT_START				=> 'dl_main_output_start',
		DeletionRendering::PRE_POST_DISPLAY					=> 'dl_pre_post_display',
		DeletionRendering::NEW_POST_HEAD_OPTION				=> 'dl_new_post_head_option',
		DeletionRendering::NEW_POST_ENTRY_DATA				=> 'dl_new_post_entry_data',
		DeletionRendering::PRE_CONFIRM_DELETE_FIELDSET		=> 'dl_pre_confirm_delete_fieldset',
		DeletionRendering::PRE_CONFIRM_DELETE_CHECKBOX		=> 'dl_pre_confirm_delete_checkbox',
		DeletionRendering::PRE_CONFIRM_DELETE_FIELDSET_END	=> 'dl_pre_confirm_delete_fieldset_end',
		DeletionRendering::CONFIRM_DELETE_FIELDSET_END		=> 'dl_confirm_delete_fieldset_end',
		DeletionRendering::END								=> 'dl_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(DeletionRendering $event): void {
		if ($event->position() === DeletionRendering::END)
			$GLOBALS['forum_id'] = $event->post()->forumId();

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
