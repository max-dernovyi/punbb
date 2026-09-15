<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Misc;

use PunBB\Module\LegacyBridge\Page\FoundName;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Misc\Api\Data\LastVisitInterface;
use PunBB\Module\Misc\Api\ReadMarksInterface;

/**
 * The query points of marking read, with the query arrays misc.php built. A
 * query a point changed answers instead; a statement a point changed runs
 * instead, and the repository is handed nothing to store. A forum's name is
 * left in $forum_name.
 */
final class ReadMarksPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	/** @return list<LastVisitInterface>|null */
	public function beforeMarkBoardRead(ReadMarksInterface $subject, LastVisitInterface ...$visits): ?array {
		$kept = array();
		foreach ($visits as $visit)
		{
			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'last_visit='.$visit->at(),
				'WHERE'		=> 'id='.$visit->userId()
			);

			if ($this->queries->changed('mi_markread_qr_update_last_visit', ReadMarksInterface::class.'::markBoardRead', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $visit;
		}

		return count($kept) !== count($visits) ? $kept : null;
	}

	public function afterForumName(ReadMarksInterface $subject, ?string $result, int $forumId, int $groupId): ?string {
		$query = array(
			'SELECT'	=> 'f.forum_name',
			'FROM'		=> 'forums AS f',
			'JOINS'		=> array(
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$forumId
		);

		if ($this->queries->changed('mi_markforumread_qr_get_forum_info', ReadMarksInterface::class.'::forumName', $query))
			$result = FoundName::of($query);

		$GLOBALS['forum_name'] = $result;

		return $result;
	}
}
