<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Edit;

use PunBB\Module\Edit\Event\EditRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the edit page's markup points at their positions, with the form's
 * counts in $forum_page, read back for the fields that follow. At the start
 * the form's action, hidden fields, attributes, help links and errors are in
 * $forum_page too, and read back; at the end the post's forum is left in
 * $forum_id, where the jump list below the page preselects it.
 */
final class EditRenderingObserver {
	public const POINTS = array(
		EditRendering::MAIN_OUTPUT_START			=> 'ed_main_output_start',
		EditRendering::PREVIEW_NEW_POST_HEAD_OPTION	=> 'ed_preview_new_post_head_option',
		EditRendering::PREVIEW_NEW_POST_ENTRY_DATA	=> 'ed_preview_new_post_entry_data',
		EditRendering::PRE_MAIN_FIELDSET			=> 'ed_pre_main_fieldset',
		EditRendering::PRE_SUBJECT					=> 'ed_pre_subject',
		EditRendering::PRE_MESSAGE_BOX				=> 'ed_pre_message_box',
		EditRendering::PRE_CHECKBOX_FIELDSET_END	=> 'ed_pre_checkbox_fieldset_end',
		EditRendering::PRE_MAIN_FIELDSET_END		=> 'ed_pre_main_fieldset_end',
		EditRendering::MAIN_FIELDSET_END			=> 'ed_main_fieldset_end',
		EditRendering::END							=> 'ed_end',
	);

	/** $forum_page key => the group of parts it holds */
	private const GROUPS = array(
		'hidden_fields'		=> EditRendering::HIDDEN_FIELDS,
		'form_attributes'	=> EditRendering::FORM_ATTRIBUTES,
		'text_options'		=> EditRendering::TEXT_OPTIONS,
		'errors'			=> EditRendering::ERRORS,
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(EditRendering $event): void {
		if ($event->position() === EditRendering::END)
			$GLOBALS['forum_id'] = $event->post()->forumId();

		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$start = $event->position() === EditRendering::MAIN_OUTPUT_START;
		if ($start)
		{
			ForumPage::set('form_action', $event->action());
			foreach (self::GROUPS as $key => $group)
			{
				$parts = array();
				foreach ($event->names($group) as $name)
					$parts[$name] = (string) $event->entry($group, $name);

				// The page set its errors only when there were some
				if ($parts !== array() || $key !== 'errors')
					ForumPage::set($key, $parts);
			}
		}

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));

		if ($start)
		{
			foreach (self::GROUPS as $key => $group)
			{
				foreach ($event->names($group) as $name)
					$event->remove($group, $name);

				foreach (Markers::entries(ForumPage::get($key)) as $name => $markup)
					$event->set($group, (string) $name, $markup);
			}
		}
	}
}
