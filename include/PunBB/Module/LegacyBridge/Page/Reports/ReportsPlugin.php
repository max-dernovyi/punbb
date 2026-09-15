<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Reports;

use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Reports\Api\Data\ReportInterface;
use PunBB\Module\Reports\Api\ReportsInterface;

/**
 * The reports page's query points, with the query arrays admin/reports.php
 * built. The lists are left in $unread_reports and $zapped_reports, as the
 * page script kept them. Marking reports read is a statement: when a point
 * changed it, the changed one runs instead, and the repository is handed no
 * report to mark.
 */
final class ReportsPlugin {
	private const JOINS = array(
		array(
			'LEFT JOIN'		=> 'posts AS p',
			'ON'			=> 'r.post_id=p.id'
		),
		array(
			'LEFT JOIN'		=> 'topics AS t',
			'ON'			=> 'r.topic_id=t.id'
		),
		array(
			'LEFT JOIN'		=> 'forums AS f',
			'ON'			=> 'r.forum_id=f.id'
		),
		array(
			'LEFT JOIN'		=> 'users AS u',
			'ON'			=> 'r.reported_by=u.id'
		)
	);

	public function __construct(private readonly PluggedQuery $queries, private readonly ReportRows $rows) {}

	/**
	 * @param list<ReportInterface> $result
	 * @return list<ReportInterface>
	 */
	public function afterUnread(ReportsInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'r.id, r.topic_id, r.forum_id, r.reported_by, r.created, r.message, p.id AS pid, t.subject, f.forum_name, u.username AS reporter',
			'FROM'		=> 'reports AS r',
			'JOINS'		=> self::JOINS,
			'WHERE'		=> 'r.zapped IS NULL',
			'ORDER BY'	=> 'r.created DESC'
		);

		if ($this->queries->changed('arp_qr_get_new_reports', ReportsInterface::class.'::unread', $query))
			$result = $this->answer($query);

		$GLOBALS['unread_reports'] = array_map($this->rows->row(...), $result);
		self::flag('new_reports', $result !== array());

		return $result;
	}

	/**
	 * @param list<ReportInterface> $result
	 * @return list<ReportInterface>
	 */
	public function afterRecentlyRead(ReportsInterface $subject, array $result, int $limit): array {
		$joins = self::JOINS;
		$joins[] = array(
			'LEFT JOIN'		=> 'users AS u2',
			'ON'			=> 'r.zapped_by=u2.id'
		);

		$query = array(
			'SELECT'	=> 'r.id, r.topic_id, r.forum_id, r.reported_by, r.created, r.message, r.zapped, r.zapped_by AS zapped_by_id, p.id AS pid, t.subject, f.forum_name, u.username AS reporter, u2.username AS zapped_by',
			'FROM'		=> 'reports AS r',
			'JOINS'		=> $joins,
			'WHERE'		=> 'r.zapped IS NOT NULL',
			'ORDER BY'	=> 'r.zapped DESC',
			'LIMIT'		=> (string) $limit
		);

		if ($this->queries->changed('arp_qr_get_last_zapped_reports', ReportsInterface::class.'::recentlyRead', $query))
			$result = $this->answer($query);

		$GLOBALS['zapped_reports'] = array_map($this->rows->row(...), $result);
		self::flag('old_reports', $result !== array());

		return $result;
	}

	/**
	 * @param list<int> $reportIds
	 * @return list<mixed>|null
	 */
	public function beforeMarkRead(ReportsInterface $subject, array $reportIds, int $userId, int $now): ?array {
		$GLOBALS['reports_to_mark'] = $reportIds;

		$query = array(
			'UPDATE'	=> 'reports',
			'SET'		=> 'zapped='.$now.', zapped_by='.$userId,
			'WHERE'		=> 'id IN('.implode(',', $reportIds).') AND zapped IS NULL'
		);

		if (!$this->queries->changed('arp_mark_as_read_qr_mark_reports_as_read', ReportsInterface::class.'::markRead', $query))
			return null;

		PluggedQuery::run($query);

		return array(array(), $userId, $now);
	}

	/**
	 * @param array<string, mixed> $query
	 * @return list<ReportInterface>
	 */
	private function answer(array $query): array {
		$reports = array();
		foreach (PluggedQuery::rows($query) as $row)
		{
			$report = ReportRows::report($row);
			$this->rows->keep($report, $row);
			$reports[] = $report;
		}

		return $reports;
	}

	private static function flag(string $key, bool $value): void {
		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page[$key] = $value;
		$GLOBALS['forum_page'] = $page;
	}
}
