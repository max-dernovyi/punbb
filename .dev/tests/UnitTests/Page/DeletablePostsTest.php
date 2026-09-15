<?php
/**
 * The deletion page's repository over an in-memory SQLite database with the
 * forum's tables: a post with its topic and forum as a group may read it, and
 * the post before another in its topic.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Delete\Model\DeletablePosts;

class DeletablePostsTest extends TestCase {
	private DeletablePosts $posts;

	protected function setUp(): void {
		$db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');

		foreach (array(
			'CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80) NOT NULL, moderators TEXT)',
			'CREATE TABLE pun_forum_perms (group_id INTEGER NOT NULL, forum_id INTEGER NOT NULL, read_forum INTEGER NOT NULL)',
			'CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, forum_id INTEGER NOT NULL, subject VARCHAR(255) NOT NULL, first_post_id INTEGER NOT NULL, closed INTEGER NOT NULL DEFAULT 0)',
			'CREATE TABLE pun_posts (id INTEGER PRIMARY KEY, topic_id INTEGER NOT NULL, poster VARCHAR(200) NOT NULL, poster_id INTEGER NOT NULL, message TEXT, hide_smilies INTEGER NOT NULL DEFAULT 0, posted INTEGER NOT NULL)',
		) as $table)
			$db->execute($table);

		$db->execute('INSERT INTO pun_forums (id, forum_name, moderators) VALUES (1, ?, ?), (2, ?, NULL)', 'Open', serialize(array('mod' => 7)), 'Staff');
		$db->execute('INSERT INTO pun_forum_perms (group_id, forum_id, read_forum) VALUES (3, 2, 0)');
		$db->execute('INSERT INTO pun_topics (id, forum_id, subject, first_post_id, closed) VALUES (1, 1, ?, 1, 0), (2, 2, ?, 4, 1)', 'Hello', 'Staff room');

		foreach (array(array(1, 1, 'anna', 3, 'first', 0, 100), array(2, 1, 'bob', 4, 'second', 1, 200), array(3, 1, 'anna', 3, null, 0, 300), array(4, 2, 'mod', 7, 'staff', 0, 400)) as $post)
			$db->execute('INSERT INTO pun_posts (id, topic_id, poster, poster_id, message, hide_smilies, posted) VALUES (?, ?, ?, ?, ?, ?, ?)', ...$post);

		$this->posts = new DeletablePosts($db);
	}

	public function testAPostCarriesItsTopicAndForum(): void {
		$post = $this->posts->find(2, 1);

		$this->assertNotNull($post);
		$this->assertSame(array(2, 1, 'Open', 1, 'Hello', 1, false, false, 'bob', 4, 'second', true, 200),
			array($post->id(), $post->forumId(), $post->forumName(), $post->topicId(), $post->subject(), $post->firstPostId(), $post->isTopic(), $post->topicClosed(),
				$post->poster(), $post->posterId(), $post->message(), $post->hidesSmilies(), $post->posted()));
		$this->assertSame(array(array(7, 'mod')), array_map(static fn ($moderator): array => array($moderator->userId(), $moderator->username()), $post->moderators()));
	}

	public function testTheFirstPostOfATopicIsTheTopic(): void {
		$this->assertTrue($this->posts->find(1, 1)?->isTopic());
		$this->assertSame('', $this->posts->find(3, 1)?->message());
	}

	public function testAPostInAForumTheGroupMayNotReadIsNotThere(): void {
		$this->assertNull($this->posts->find(4, 3));
		$this->assertTrue($this->posts->find(4, 1)?->topicClosed());
		$this->assertSame(array(), $this->posts->find(4, 1)?->moderators());
		$this->assertNull($this->posts->find(99, 1));
	}

	public function testThePostBeforeAnotherIsTheNearestInItsTopic(): void {
		$this->assertSame(2, $this->posts->previousPostId(1, 3));
		$this->assertSame(1, $this->posts->previousPostId(1, 2));
		$this->assertNull($this->posts->previousPostId(1, 1));
		$this->assertNull($this->posts->previousPostId(2, 4));
	}
}
