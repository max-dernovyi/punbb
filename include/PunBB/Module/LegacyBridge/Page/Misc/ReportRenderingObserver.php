<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Misc;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Misc\Event\ReportRendering;

/**
 * Renders the markup points of the form reporting a post at their positions, with
 * the form's counts in $forum_page, read back for the fields that follow. At
 * the start the form's action and hidden fields are in $forum_page too, and
 * before the errors the errors; both are read back.
 */
final class ReportRenderingObserver {
	public const POINTS = array(
		ReportRendering::OUTPUT_START			=> 'mi_report_output_start',
		ReportRendering::PRE_REPORT_ERRORS		=> 'mi_pre_report_errors',
		ReportRendering::PRE_FIELDSET			=> 'mi_report_pre_fieldset',
		ReportRendering::PRE_REASON				=> 'mi_report_pre_reason',
		ReportRendering::PRE_FIELDSET_END		=> 'mi_report_pre_fieldset_end',
		ReportRendering::FIELDSET_END			=> 'mi_report_fieldset_end',
		ReportRendering::END					=> 'mi_report_end',
	);

	/** The group of parts each position publishes in $forum_page, by key. */
	private const GROUPS = array(
		ReportRendering::OUTPUT_START		=> array('hidden_fields', ReportRendering::HIDDEN_FIELDS),
		ReportRendering::PRE_REPORT_ERRORS	=> array('errors', ReportRendering::ERRORS),
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ReportRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$group = self::GROUPS[$event->position()] ?? null;
		if ($group !== null)
		{
			$parts = array();
			foreach ($event->names($group[1]) as $name)
				$parts[$name] = (string) $event->entry($group[1], $name);

			ForumPage::set($group[0], $parts);
		}

		if ($event->position() === ReportRendering::OUTPUT_START)
			ForumPage::set('form_action', $event->action());

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));

		if ($group !== null)
		{
			foreach ($event->names($group[1]) as $name)
				$event->remove($group[1], $name);

			foreach (Markers::entries(ForumPage::get($group[0])) as $name => $markup)
				$event->set($group[1], (string) $name, $markup);
		}
	}
}
