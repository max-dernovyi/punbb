<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Reports;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Reports\Event\ReportMarkingStep;

/**
 * Runs the point at each step of marking reports read.
 */
final class ReportMarkingObserver {
	public const POINTS = array(
		ReportMarkingStep::SUBMITTED	=> 'arp_mark_as_read_form_submitted',
		ReportMarkingStep::MARKED		=> 'arp_mark_as_read_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ReportMarkingStep $event): void {
		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
