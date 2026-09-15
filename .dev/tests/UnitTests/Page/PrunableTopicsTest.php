<?php
/**
 * The pruning page's repository over an in-memory SQLite database with the
 * forum's tables: the forums of this board by category, every forum's id, a
 * forum's name, and how many topics a prune takes.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Prune\Api\Data\PrunableForumInterface;
use PunBB\Module\Prune\Model\PrunableTopics;

class PrunableTopicsTest extends TestCase {
	private PrunableTopics $topics;

	protected function setUp(): void {
		$db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');

		foreach (array(
			'CREATE TABLE pun_categories (id INTEGER PRIMARY KEY, cat_name VARCHAR(80) NOT NULL, disp_position INTEGER NOT NULL)',
			'CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80) NOT NULL, redirect_url VARCHAR(100), disp_position INTEGER NOT NULL, cat_id INTEGER NOT NULL)',
			'CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, forum_id INTEGER NOT NULL, last_post INTEGER NOT NULL, sticky INTEGER NOT NULL DEFAULT 0, moved_to INTEGER)',
		) as $table)
			$db->execute($table);

		$db->execute('INSERT INTO pun_categories (id, cat_name, disp_position) VALUES (1, ?, 2), (2, ?, 1)', 'Second', 'First');
		$db->execute('INSERT INTO pun_forums (id, forum_name, redirect_url, disp_position, cat_id) VALUES (1, ?, NULL, 1, 1), (2, ?, NULL, 0, 1), (3, ?, ?, 0, 2), (4, ?, NULL, 5, 2)',
			'One', 'Two', 'Away', 'http://example.com/', 'Four');

		foreach (array(array(1, 1, 100, 0, null), array(2, 1, 200, 1, null), array(3, 1, 50, 0, 2), array(4, 2, 100, 0, null), array(5, 2, 900, 0, null)) as $topic)
			$db->execute('INSERT INTO pun_topics (id, forum_id, last_post, sticky, moved_to) VALUES (?, ?, ?, ?, ?)', ...$topic);

		$this->topics = new PrunableTopics($db);
	}

	public function testTheForumsComeByCategoryWithoutThoseOnOtherSites(): void {
		$this->assertSame(array('2 First 4 Four', '1 Second 2 Two', '1 Second 1 One'),
			array_map(static fn (PrunableForumInterface $forum): string => $forum->categoryId().' '.$forum->categoryName().' '.$forum->id().' '.$forum->name(), $this->topics->forums()));
	}

	public function testEveryForumHasItsIdAndItsName(): void {
		$this->assertSame(array(1, 2, 3, 4), $this->topics->forumIds());
		$this->assertSame('Two', $this->topics->forumName(2));
		$this->assertNull($this->topics->forumName(9));
	}

	public function testTheCountTakesTheOldTopicsThatWereNotMoved(): void {
		$this->assertSame(1, $this->topics->count(1, 300, false));
		$this->assertSame(2, $this->topics->count(1, 300, true));
		$this->assertSame(3, $this->topics->count(null, 300, true));
		$this->assertSame(0, $this->topics->count(1, 100, true));
		$this->assertSame(4, $this->topics->count(null, 1000, true));
	}
}
