<?php
/**
 * The reports page's repository over an in-memory SQLite database with the
 * forum's tables: the unread reports newest first with what they name, the
 * reports read last, and marking reports read.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Reports\Api\Data\ReportInterface;
use PunBB\Module\Reports\Model\Reports;

class ReportsTest extends TestCase {
	private Connection $db;

	private Reports $reports;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');

		foreach (array(
			'CREATE TABLE pun_reports (id INTEGER PRIMARY KEY, post_id INTEGER NOT NULL, topic_id INTEGER NOT NULL, forum_id INTEGER NOT NULL, reported_by INTEGER NOT NULL, created INTEGER NOT NULL, message TEXT, zapped INTEGER, zapped_by INTEGER)',
			'CREATE TABLE pun_posts (id INTEGER PRIMARY KEY)',
			'CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, subject VARCHAR(255) NOT NULL)',
			'CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80) NOT NULL)',
			'CREATE TABLE pun_users (id INTEGER PRIMARY KEY, username VARCHAR(200) NOT NULL)',
		) as $table)
			$this->db->execute($table);

		$this->db->execute('INSERT INTO pun_posts (id) VALUES (1), (2)');
		$this->db->execute('INSERT INTO pun_topics (id, subject) VALUES (1, ?)', 'Hello');
		$this->db->execute('INSERT INTO pun_forums (id, forum_name) VALUES (1, ?)', 'Open');
		$this->db->execute('INSERT INTO pun_users (id, username) VALUES (2, ?), (3, ?)', 'admin', 'anna');

		foreach (array(
			array(1, 1, 1, 1, 3, 100, 'first', null, null),
			array(2, 2, 1, 1, 3, 300, 'second', null, null),
			array(3, 9, 8, 7, 6, 200, null, null, null),
			array(4, 1, 1, 1, 3, 50, 'read early', 400, 2),
			array(5, 1, 1, 1, 3, 60, 'read late', 500, 9),
		) as $report)
			$this->db->execute('INSERT INTO pun_reports (id, post_id, topic_id, forum_id, reported_by, created, message, zapped, zapped_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', ...$report);

		$this->reports = new Reports($this->db);
	}

	/** @return list<int> */
	private static function ids(array $reports): array {
		return array_map(static fn (ReportInterface $report): int => $report->id(), $reports);
	}

	public function testTheUnreadReportsComeNewestFirstWithWhatTheyName(): void {
		$unread = $this->reports->unread();

		$this->assertSame(array(2, 3, 1), self::ids($unread));
		$this->assertSame(array(2, 1, 'Hello', 1, 'Open', 3, 'anna', 300, 'second', null, null, null),
			array($unread[0]->postId(), $unread[0]->topicId(), $unread[0]->subject(), $unread[0]->forumId(), $unread[0]->forumName(), $unread[0]->reporterId(), $unread[0]->reporter(),
				$unread[0]->created(), $unread[0]->message(), $unread[0]->zapped(), $unread[0]->zappedById(), $unread[0]->zappedBy()));
	}

	public function testWhatWasDeletedIsNull(): void {
		$report = $this->reports->unread()[1];

		$this->assertSame(array(null, 8, null, 7, null, 6, null, ''),
			array($report->postId(), $report->topicId(), $report->subject(), $report->forumId(), $report->forumName(), $report->reporterId(), $report->reporter(), $report->message()));
	}

	public function testTheReportsReadLastComeLastReadFirst(): void {
		$read = $this->reports->recentlyRead(10);

		$this->assertSame(array(5, 4), self::ids($read));
		$this->assertSame(array(500, 9, null), array($read[0]->zapped(), $read[0]->zappedById(), $read[0]->zappedBy()));
		$this->assertSame(array(400, 2, 'admin'), array($read[1]->zapped(), $read[1]->zappedById(), $read[1]->zappedBy()));
		$this->assertSame(array(5), self::ids($this->reports->recentlyRead(1)));
	}

	public function testMarkingReadTouchesTheUnreadReportsNamedOnly(): void {
		$this->reports->markRead(array(1, 4, 99), 3, 700);

		$this->assertSame(array(2, 3), self::ids($this->reports->unread()));
		$this->assertSame(array(1, 5, 4), self::ids($this->reports->recentlyRead(10)));
		$this->assertSame(3, $this->reports->recentlyRead(1)[0]->zappedById());
		$this->assertSame(400, $this->reports->recentlyRead(10)[2]->zapped());

		$this->reports->markRead(array(), 3, 800);
		$this->assertSame(array(2, 3), self::ids($this->reports->unread()));
	}
}
