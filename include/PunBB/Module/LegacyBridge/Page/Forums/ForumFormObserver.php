<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Forums;

use PunBB\Module\Forums\Event\ForumFormRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the points of the form editing a forum outside a group's fieldset,
 * with $forum_id, the form's counts and the lines telling how permissions work
 * in $forum_page, read back. The line about a redirect forum is the one the
 * page script appended first, under the key 0.
 */
final class ForumFormObserver {
	public const POINTS = array(
		ForumFormRendering::OUTPUT_START				=> 'afo_edit_forum_output_start',
		ForumFormRendering::PRE_DETAILS_FIELDSET		=> 'afo_edit_forum_pre_details_fieldset',
		ForumFormRendering::PRE_FORUM_NAME				=> 'afo_edit_forum_pre_forum_name',
		ForumFormRendering::PRE_FORUM_DESCRIP			=> 'afo_edit_forum_pre_forum_descrip',
		ForumFormRendering::PRE_FORUM_CAT				=> 'afo_edit_forum_pre_forum_cat',
		ForumFormRendering::PRE_FORUM_SORT_BY			=> 'afo_edit_forum_pre_forum_sort_by',
		ForumFormRendering::MODIFY_SORT_BY				=> 'afo_edit_forum_modify_sort_by',
		ForumFormRendering::PRE_FORUM_REDIRECT_URL		=> 'afo_edit_forum_pre_forum_redirect_url',
		ForumFormRendering::PRE_DETAILS_FIELDSET_END	=> 'afo_edit_forum_pre_details_fieldset_end',
		ForumFormRendering::DETAILS_FIELDSET_END		=> 'afo_edit_forum_details_fieldset_end',
		ForumFormRendering::PRE_PERMISSIONS_PART		=> 'afo_edit_forum_pre_permissions_part',
		ForumFormRendering::END							=> 'afo_edit_forum_end',
	);

	/** The name of the line the page script appended without one. */
	private const FIRST_LINE = 'redirect';

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ForumFormRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['forum_id'] = $event->forum()->id();

		if ($event->carriesLines())
		{
			$lines = array();
			foreach ($event->names() as $name)
				$lines[$name === self::FIRST_LINE ? 0 : $name] = (string) $event->entry($name);

			ForumPage::set('form_info', $lines);
		}

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));

		if ($event->carriesLines())
		{
			$lines = Markers::entries(ForumPage::get('form_info'));
			foreach ($event->names() as $name)
				if (!isset($lines[$name === self::FIRST_LINE ? 0 : $name]))
					$event->remove($name);

			foreach ($lines as $name => $markup)
				$event->set($name === 0 ? self::FIRST_LINE : (string) $name, $markup);
		}
	}
}
