<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Misc\Api\Data\LastVisitInterface;
use PunBB\Module\Misc\Api\ReadMarksInterface;

/**
 * The last visits in the users table, and the forums a group may read.
 */
final class ReadMarks implements ReadMarksInterface {
	public function __construct(private readonly Connection $db) {}

	public function markBoardRead(LastVisitInterface ...$visits): void {
		foreach ($visits as $visit)
			$this->db->execute('UPDATE '.$this->db->table('users').' SET last_visit=? WHERE id=?', $visit->at(), $visit->userId());
	}

	public function forumName(int $forumId, int $groupId): ?string {
		$name = $this->db->selectValue('SELECT f.forum_name FROM '.$this->db->table('forums').' AS f'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)'.
			' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id=?', $groupId, $forumId);

		return $name !== null ? (string) $name : null;
	}
}
