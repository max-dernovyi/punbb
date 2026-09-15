<?php
/**
 * The ranks page's repository over an in-memory SQLite database with the
 * forum's table, whose title column is named after a reserved word: the ranks
 * from the fewest posts, whether a number of posts is taken, and adding,
 * updating and removing ranks.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Ranks\Api\Data\RankInterface;
use PunBB\Module\Ranks\Model\Rank;
use PunBB\Module\Ranks\Model\Ranks;

class RanksTest extends TestCase {
	private Ranks $ranks;

	protected function setUp(): void {
		$db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');
		$db->execute('CREATE TABLE pun_ranks (id INTEGER PRIMARY KEY, "rank" VARCHAR(50) NOT NULL, min_posts INTEGER NOT NULL)');
		$db->execute('INSERT INTO pun_ranks (id, "rank", min_posts) VALUES (1, ?, 10), (2, ?, 0)', 'Member', 'New member');

		$this->ranks = new Ranks($db);
	}

	/** @return list<string> */
	private function listed(): array {
		return array_map(static fn (RankInterface $rank): string => $rank->id().' '.$rank->title().' '.$rank->minPosts(), $this->ranks->all());
	}

	public function testTheRanksComeFromTheFewestPosts(): void {
		$this->assertSame(array('2 New member 0', '1 Member 10'), $this->listed());
	}

	public function testANumberOfPostsIsTakenByAnyOtherRank(): void {
		$this->assertTrue($this->ranks->minPostsTaken(10, null));
		$this->assertFalse($this->ranks->minPostsTaken(10, 1));
		$this->assertTrue($this->ranks->minPostsTaken(10, 2));
		$this->assertFalse($this->ranks->minPostsTaken(5, null));
	}

	public function testRanksAreAddedUpdatedAndRemoved(): void {
		$this->ranks->add(new Rank(0, 'Veteran', 100));
		$this->ranks->update(new Rank(1, 'Regular', 20));
		$this->ranks->remove(2);

		$this->assertSame(array('1 Regular 20', '3 Veteran 100'), $this->listed());
	}
}
