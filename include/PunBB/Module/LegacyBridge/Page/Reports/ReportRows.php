<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Reports;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\Reports\Api\Data\ReportInterface;
use PunBB\Module\Reports\Model\Report;

/**
 * A report as admin/reports.php handed it to extension code: the row of its
 * query, with any column a query point added.
 */
final class ReportRows {
	public function __construct(private readonly KeptRows $rows) {}

	/** @param array<array-key, mixed> $row */
	public function keep(ReportInterface $report, array $row): void {
		$this->rows->keep($report, $row);
	}

	/** @return array<array-key, mixed> the row the query returned for $report, or one built from it */
	public function row(ReportInterface $report): array {
		$row = $this->rows->row($report);
		if ($row !== null)
			return $row;

		$row = array(
			'id'			=> $report->id(),
			'topic_id'		=> $report->topicId(),
			'forum_id'		=> $report->forumId(),
			'reported_by'	=> $report->reporterId(),
			'created'		=> $report->created(),
			'message'		=> $report->message(),
		);

		if ($report->zapped() !== null)
			$row += array('zapped' => $report->zapped(), 'zapped_by_id' => $report->zappedById());

		$row += array('pid' => $report->postId(), 'subject' => $report->subject(), 'forum_name' => $report->forumName(), 'reporter' => $report->reporter());

		if ($report->zapped() !== null)
			$row['zapped_by'] = $report->zappedBy();

		return $row;
	}

	/** @param array<array-key, mixed> $row a row of the unread or the read reports' query */
	public static function report(array $row): Report {
		return new Report(
			(int) Markers::markup($row['id'] ?? 0),
			isset($row['pid']) && Markers::markup($row['pid']) !== '' ? (int) Markers::markup($row['pid']) : null,
			(int) Markers::markup($row['topic_id'] ?? 0),
			isset($row['subject']) ? Markers::markup($row['subject']) : null,
			(int) Markers::markup($row['forum_id'] ?? 0),
			isset($row['forum_name']) ? Markers::markup($row['forum_name']) : null,
			(int) Markers::markup($row['reported_by'] ?? 0),
			isset($row['reporter']) ? Markers::markup($row['reporter']) : null,
			(int) Markers::markup($row['created'] ?? 0),
			Markers::markup($row['message'] ?? ''),
			isset($row['zapped']) ? (int) Markers::markup($row['zapped']) : null,
			isset($row['zapped_by_id']) ? (int) Markers::markup($row['zapped_by_id']) : null,
			isset($row['zapped_by']) ? Markers::markup($row['zapped_by']) : null
		);
	}
}
