<?php
/**
 * Syndication's repository over an in-memory SQLite database with the forum's
 * tables: a topic a group may read, its posts last first, a forum's name, the
 * recent topics narrowed to forums or excluding them, who is online and the
 * board's statistics.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Extern\Api\Data\FeedEntryInterface;
use PunBB\Module\Extern\Model\Syndication;

class SyndicationTest extends TestCase {
	private Syndication $syndication;

	protected function setUp(): void {
		$db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');

		foreach (array(
			'CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80) NOT NULL, num_topics INTEGER NOT NULL DEFAULT 0, num_posts INTEGER NOT NULL DEFAULT 0)',
			'CREATE TABLE pun_forum_perms (group_id INTEGER NOT NULL, forum_id INTEGER NOT NULL, read_forum INTEGER NOT NULL)',
			'CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, forum_id INTEGER NOT NULL, poster VARCHAR(200) NOT NULL, subject VARCHAR(255) NOT NULL, posted INTEGER NOT NULL, first_post_id INTEGER NOT NULL, last_post INTEGER NOT NULL, moved_to INTEGER)',
			'CREATE TABLE pun_posts (id INTEGER PRIMARY KEY, topic_id INTEGER NOT NULL, poster VARCHAR(200) NOT NULL, poster_id INTEGER NOT NULL, poster_email VARCHAR(80), message TEXT, hide_smilies INTEGER NOT NULL DEFAULT 0, posted INTEGER NOT NULL)',
			'CREATE TABLE pun_users (id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL, username VARCHAR(200) NOT NULL, email VARCHAR(80) NOT NULL, email_setting INTEGER NOT NULL DEFAULT 1, registered INTEGER NOT NULL)',
			'CREATE TABLE pun_online (user_id INTEGER NOT NULL, ident VARCHAR(200) NOT NULL, idle INTEGER NOT NULL DEFAULT 0)',
		) as $table)
			$db->execute($table);

		$db->execute('INSERT INTO pun_forums (id, forum_name, num_topics, num_posts) VALUES (1, ?, 2, 3), (2, ?, 1, 1)', 'Open', 'Staff');
		$db->execute('INSERT INTO pun_forum_perms (group_id, forum_id, read_forum) VALUES (3, 2, 0)');
		$db->execute('INSERT INTO pun_users (id, group_id, username, email, email_setting, registered) VALUES (1, 2, ?, ?, 1, 0), (2, 1, ?, ?, 0, 10), (3, 3, ?, ?, 1, 30), (4, 0, ?, ?, 1, 40)',
			'Guest', '', 'admin', 'admin@example.com', 'anna', 'anna@example.com', 'unverified', 'u@example.com');
		$db->execute('INSERT INTO pun_topics (id, forum_id, poster, subject, posted, first_post_id, last_post, moved_to) VALUES (1, 1, ?, ?, 100, 1, 500, NULL), (2, 1, ?, ?, 200, 3, 300, NULL), (3, 2, ?, ?, 400, 4, 400, NULL), (4, 1, ?, ?, 450, 1, 450, 1)',
			'admin', 'Hello', 'Guest', 'By a guest', 'admin', 'Staff only', 'admin', 'Moved');
		$db->execute('INSERT INTO pun_posts (id, topic_id, poster, poster_id, poster_email, message, hide_smilies, posted) VALUES (1, 1, ?, 2, NULL, ?, 1, 100), (2, 1, ?, 3, NULL, NULL, 0, 500), (3, 2, ?, 1, ?, ?, 0, 200), (4, 3, ?, 2, NULL, ?, 0, 400)',
			'admin', 'first', 'anna', 'Guest', 'guest@example.com', 'guest text', 'admin', 'staff');
		$db->execute('INSERT INTO pun_online (user_id, ident, idle) VALUES (1, ?, 0), (3, ?, 0), (2, ?, 1), (2, ?, 0)', '192.0.2.9', 'anna', 'admin idle', 'admin');

		$this->syndication = new Syndication($db);
	}

	private static function describe(FeedEntryInterface $entry): string {
		return implode('|', array($entry->id(), $entry->subject(), $entry->poster(), $entry->posterId(), $entry->posted(), $entry->message(), (int) $entry->hidesSmilies(),
			$entry->accountEmail(), (int) $entry->showsEmail(), $entry->guestEmail()));
	}

	public function testATopicTheGroupMayReadUnlessItMoved(): void {
		$this->assertSame(array(1, 'Hello', 1), array($this->syndication->topic(1, 3)?->id(), $this->syndication->topic(1, 3)?->subject(), $this->syndication->topic(1, 3)?->firstPostId()));
		$this->assertNull($this->syndication->topic(3, 3));
		$this->assertNotNull($this->syndication->topic(3, 1));
		$this->assertNull($this->syndication->topic(4, 1));
	}

	public function testATopicsPostsComeLastFirst(): void {
		$this->assertSame(array('2||anna|3|500||0|anna@example.com|0|', '1||admin|2|100|first|1|admin@example.com|1|'), array_map(self::describe(...), $this->syndication->posts(1, 10)));
		$this->assertCount(1, $this->syndication->posts(1, 1));
	}

	public function testAForumsNameAsTheGroupMayReadIt(): void {
		$this->assertSame('Open', $this->syndication->forumName(1, 3));
		$this->assertNull($this->syndication->forumName(2, 3));
		$this->assertSame('Staff', $this->syndication->forumName(2, 1));
	}

	public function testTheRecentTopicsWithTheirFirstPosts(): void {
		$ids = fn (array $entries): array => array_map(static fn (FeedEntryInterface $entry): int => $entry->id(), $entries);

		$this->assertSame(array(3, 2, 1), $ids($this->syndication->topics(1, array(), false, false, 10)));
		$this->assertSame(array(1, 3, 2), $ids($this->syndication->topics(1, array(), false, true, 10)));
		$this->assertSame(array(2, 1), $ids($this->syndication->topics(3, array(), false, false, 10)), 'a forum the group may not read is left out');
		$this->assertSame(array(3), $ids($this->syndication->topics(1, array(2, 9), false, false, 10)));
		$this->assertSame(array(2, 1), $ids($this->syndication->topics(1, array(2), true, false, 10)));
		$this->assertSame(array(3), $ids($this->syndication->topics(1, array(), false, false, 1)));
		$this->assertSame('2|By a guest|Guest|1|200|guest text|0||0|guest@example.com', self::describe($this->syndication->topics(1, array(), false, false, 10)[1]));
	}

	public function testWhoIsOnlineAndTheStatistics(): void {
		$this->assertSame(array('192.0.2.9 1', 'admin 0', 'anna 0'), array_map(static fn ($visitor): string => $visitor->ident().' '.(int) $visitor->isGuest(), $this->syndication->onlineVisitors()));

		$statistics = $this->syndication->statistics();
		$this->assertSame(array(2, 3, 'anna', 3, 4), array($statistics->userCount(), $statistics->newestUserId(), $statistics->newestUsername(), $statistics->topicCount(), $statistics->postCount()));
	}
}
