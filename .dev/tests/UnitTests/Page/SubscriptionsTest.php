<?php
/**
 * The subscriptions and read marks of misc.php over an in-memory SQLite
 * database with the forum's tables: what a group may read, subscribing and
 * unsubscribing to topics and forums, and moving a member's last visit.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Misc\Model\LastVisit;
use PunBB\Module\Misc\Model\ReadMarks;
use PunBB\Module\Misc\Model\Subscription;
use PunBB\Module\Misc\Model\Subscriptions;

class SubscriptionsTest extends TestCase {
	private Connection $db;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');
		$this->db->execute('CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80) NOT NULL)');
		$this->db->execute('CREATE TABLE pun_forum_perms (group_id INTEGER NOT NULL, forum_id INTEGER NOT NULL, read_forum TINYINT NOT NULL DEFAULT 1)');
		$this->db->execute('CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, subject VARCHAR(255) NOT NULL, forum_id INTEGER NOT NULL, moved_to INTEGER)');
		$this->db->execute('CREATE TABLE pun_subscriptions (user_id INTEGER NOT NULL, topic_id INTEGER NOT NULL)');
		$this->db->execute('CREATE TABLE pun_forum_subscriptions (user_id INTEGER NOT NULL, forum_id INTEGER NOT NULL)');
		$this->db->execute('CREATE TABLE pun_users (id INTEGER PRIMARY KEY, last_visit INTEGER NOT NULL DEFAULT 0)');
		$this->db->execute('INSERT INTO pun_forums (id, forum_name) VALUES (1, ?), (2, ?)', 'News', 'Staff');
		$this->db->execute('INSERT INTO pun_forum_perms (group_id, forum_id, read_forum) VALUES (3, 2, 0)');
		$this->db->execute('INSERT INTO pun_topics (id, subject, forum_id, moved_to) VALUES (1, ?, 1, NULL), (2, ?, 2, NULL), (3, ?, 1, 1)', 'Hello', 'Secret', 'Moved');
		$this->db->execute('INSERT INTO pun_subscriptions (user_id, topic_id) VALUES (5, 1)');
		$this->db->execute('INSERT INTO pun_forum_subscriptions (user_id, forum_id) VALUES (5, 1)');
		$this->db->execute('INSERT INTO pun_users (id, last_visit) VALUES (5, 10)');
	}

	public function testATopicOrForumIsFoundWhenTheGroupMayReadIt(): void {
		$subscriptions = new Subscriptions($this->db);

		$this->assertSame('Hello', $subscriptions->topicSubject(1, 3));
		$this->assertNull($subscriptions->topicSubject(2, 3));
		$this->assertSame('Secret', $subscriptions->topicSubject(2, 1));
		$this->assertNull($subscriptions->topicSubject(3, 1), 'a moved topic is no one to subscribe to');
		$this->assertSame('News', $subscriptions->forumName(1, 3));
		$this->assertNull($subscriptions->forumName(2, 3));
		$this->assertSame('Staff', $subscriptions->unsubscribingForumName(2, 1));
		$this->assertSame('News', (new ReadMarks($this->db))->forumName(1, 3));
		$this->assertNull((new ReadMarks($this->db))->forumName(9, 3));
	}

	public function testSubscriptionsAreAddedCheckedAndRemoved(): void {
		$subscriptions = new Subscriptions($this->db);

		$this->assertTrue($subscriptions->isSubscribedToTopic(5, 1));
		$this->assertFalse($subscriptions->isSubscribedToTopic(6, 1));
		$this->assertSame('Hello', $subscriptions->subscribedTopicSubject(5, 1));
		$this->assertNull($subscriptions->subscribedTopicSubject(6, 1));

		$subscriptions->subscribeToTopic(new Subscription(6, 1));
		$subscriptions->subscribeToForum(new Subscription(6, 2));
		$subscriptions->unsubscribeFromTopic(new Subscription(5, 1));
		$subscriptions->unsubscribeFromForum(new Subscription(5, 1));

		$this->assertTrue($subscriptions->isSubscribedToTopic(6, 1));
		$this->assertFalse($subscriptions->isSubscribedToTopic(5, 1));
		$this->assertTrue($subscriptions->isSubscribedToForum(6, 2));
		$this->assertFalse($subscriptions->isSubscribedToForum(5, 1));
	}

	public function testMarkingTheBoardReadMovesTheLastVisit(): void {
		(new ReadMarks($this->db))->markBoardRead(new LastVisit(5, 2000));

		$this->assertSame(2000, (int) $this->db->selectValue('SELECT last_visit FROM pun_users WHERE id=5'));
	}
}
