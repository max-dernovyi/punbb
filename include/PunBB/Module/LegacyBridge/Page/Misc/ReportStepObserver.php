<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Misc;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Misc\Event\ReportStep;

/**
 * Runs the point at each step of reporting a post, with the report in the
 * variables misc.php held it in: $post_id, $errors, $reason once checked, and
 * $mail_subject and $mail_message while it is mailed, both read back.
 */
final class ReportStepObserver {
	public const POINTS = array(
		ReportStep::SELECTED	=> 'mi_report_selected',
		ReportStep::SUBMITTED	=> 'mi_report_form_submitted',
		ReportStep::REPORTING	=> 'mi_report_pre_reports_sent',
		ReportStep::MAILING		=> 'mi_report_modify_message',
		ReportStep::REPORTED	=> 'mi_report_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ReportStep $event): void {
		$GLOBALS['post_id'] = $event->postId();

		if ($event->step() === ReportStep::SELECTED || $event->step() === ReportStep::SUBMITTED)
			$GLOBALS['errors'] = array();

		if (!in_array($event->step(), array(ReportStep::SELECTED, ReportStep::SUBMITTED), true))
			$GLOBALS['reason'] = $event->reason();

		if ($event->step() === ReportStep::MAILING)
		{
			$GLOBALS['mail_subject'] = $event->mailSubject();
			$GLOBALS['mail_message'] = $event->mailMessage();
		}

		$point = self::POINTS[$event->step()];
		if (!LegacyScope::attached($point))
			return;

		$this->scope->observe($point, $event);

		if ($event->step() === ReportStep::MAILING)
			$event->compose(Markers::markup($GLOBALS['mail_subject'] ?? ''), Markers::markup($GLOBALS['mail_message'] ?? ''));
	}
}
