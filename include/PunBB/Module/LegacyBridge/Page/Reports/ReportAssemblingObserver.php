<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Reports;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Reports\Event\ReportAssembling;

/**
 * Runs the point at each stage of a report's block with the report as
 * $cur_report, its parts in the variables admin/reports.php held them in and
 * the counts in $forum_page, all read back.
 */
final class ReportAssemblingObserver {
	/** Whether the report is read => stage => point */
	public const POINTS = array(
		0 => array(
			ReportAssembling::PARTS		=> 'arp_new_report_pre_display',
			ReportAssembling::BLOCK_END	=> 'arp_new_report_new_block',
		),
		1 => array(
			ReportAssembling::PARTS		=> 'arp_report_pre_display',
			ReportAssembling::BLOCK_END	=> 'arp_report_new_block',
		),
	);

	/** Part => the variable the page script held it in */
	private const VARIABLES = array(
		'reporter'	=> 'reporter',
		'forum'		=> 'forum',
		'topic'		=> 'topic',
		'message'	=> 'message',
		'post'		=> 'post_id',
		'zapped_by'	=> 'zapped_by',
	);

	public function __construct(private readonly PageScope $scope, private readonly ReportRows $rows) {}

	public function observe(ReportAssembling $event): void {
		$point = self::POINTS[$event->isRead() ? 1 : 0][$event->stage()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['cur_report'] = $this->rows->row($event->report());

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page['item_count'] = $event->itemCount();
		$page['fld_count'] = $event->fieldCount();
		$page['item_num'] = $event->stage() === ReportAssembling::PARTS ? $event->number() - 1 : $event->number();
		$GLOBALS['forum_page'] = $page;

		$parts = $event->stage() === ReportAssembling::PARTS;
		if ($parts)
			foreach (self::VARIABLES as $part => $variable)
				if ($event->entry($part) !== null)
					$GLOBALS[$variable] = $event->entry($part);

		$event->append($this->scope->renderObserved($point, $event));

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$event->count((int) Markers::markup($page['item_count'] ?? $event->itemCount()), (int) Markers::markup($page['fld_count'] ?? $event->fieldCount()));

		if ($parts)
			foreach (self::VARIABLES as $part => $variable)
				if ($event->entry($part) !== null)
					$event->set($part, Markers::markup($GLOBALS[$variable] ?? ''));
	}
}
