<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewtopic;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Viewtopic\Event\QuickPostRendering;

/**
 * Renders the quick reply form's markup points at their positions. Before it
 * is displayed, its action, hidden fields, attributes and help links are in
 * $forum_page, and read back.
 */
final class QuickPostObserver {
	public const POINTS = array(
		QuickPostRendering::OUTPUT_START		=> 'vt_qpost_output_start',
		QuickPostRendering::PRE_DISPLAY			=> 'vt_quickpost_pre_display',
		QuickPostRendering::PRE_FIELDSET		=> 'vt_quickpost_pre_fieldset',
		QuickPostRendering::PRE_MESSAGE_BOX		=> 'vt_quickpost_pre_message_box',
		QuickPostRendering::PRE_FIELDSET_END	=> 'vt_quickpost_pre_fieldset_end',
		QuickPostRendering::FIELDSET_END		=> 'vt_quickpost_fieldset_end',
		QuickPostRendering::END					=> 'vt_quickpost_end',
	);

	/** $forum_page key => the group of parts it holds */
	private const GROUPS = array(
		'hidden_fields'		=> QuickPostRendering::HIDDEN_FIELDS,
		'form_attributes'	=> QuickPostRendering::FORM_ATTRIBUTES,
		'text_options'		=> QuickPostRendering::TEXT_OPTIONS,
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(QuickPostRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$display = $event->position() === QuickPostRendering::PRE_DISPLAY;
		if ($display)
		{
			ForumPage::set('form_action', $event->action());
			ForumPage::set('main_head_options', array());
			foreach (self::GROUPS as $key => $group)
			{
				$parts = array();
				foreach ($event->names($group) as $name)
					$parts[$name] = (string) $event->entry($group, $name);

				// The page left the help links unset when the board allows none
				if ($parts !== array() || $key !== 'text_options')
					ForumPage::set($key, $parts);
			}
		}

		$event->append($this->scope->renderObserved($point, $event));

		if (!$display)
			return;

		foreach (self::GROUPS as $key => $group)
		{
			foreach ($event->names($group) as $name)
				$event->remove($group, $name);

			foreach (Markers::entries(ForumPage::get($key)) as $name => $markup)
				$event->set($group, (string) $name, $markup);
		}
	}
}
