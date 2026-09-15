<?php
/**
 * The posting page's repository over an in-memory SQLite database with the
 * forum's tables: a topic with its forum and the member's subscription, a
 * forum, as a group may read them, the post a reply quotes, and the newest
 * posts of a topic.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Post\Model\Posting;

class PostingTest extends TestCase {
	private Posting $posting;

	protected function setUp(): void {
		$db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');

		foreach (array(
			'CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80) NOT NULL, moderators TEXT, redirect_url VARCHAR(100))',
			'CREATE TABLE pun_forum_perms (group_id INTEGER NOT NULL, forum_id INTEGER NOT NULL, read_forum INTEGER NOT NULL, post_replies INTEGER NOT NULL, post_topics INTEGER NOT NULL)',
			'CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, forum_id INTEGER NOT NULL, subject VARCHAR(255) NOT NULL, closed INTEGER NOT NULL DEFAULT 0)',
			'CREATE TABLE pun_subscriptions (user_id INTEGER NOT NULL, topic_id INTEGER NOT NULL)',
			'CREATE TABLE pun_posts (id INTEGER PRIMARY KEY, topic_id INTEGER NOT NULL, poster VARCHAR(200) NOT NULL, message TEXT, hide_smilies INTEGER NOT NULL DEFAULT 0, posted INTEGER NOT NULL)',
		) as $table)
			$db->execute($table);

		$db->execute('INSERT INTO pun_forums (id, forum_name, moderators, redirect_url) VALUES (1, ?, ?, NULL), (2, ?, NULL, NULL), (3, ?, NULL, ?)', 'Open', serialize(array('mod' => 7)), 'Staff', 'Away', 'http://example.com/');
		$db->execute('INSERT INTO pun_forum_perms (group_id, forum_id, read_forum, post_replies, post_topics) VALUES (3, 2, 0, 1, 1), (4, 2, 1, 0, 1)');
		$db->execute('INSERT INTO pun_topics (id, forum_id, subject, closed) VALUES (1, 1, ?, 0), (2, 2, ?, 1)', 'Hello', 'Staff room');
		$db->execute('INSERT INTO pun_subscriptions (user_id, topic_id) VALUES (5, 1)');
		$db->execute('INSERT INTO pun_posts (id, topic_id, poster, message, hide_smilies, posted) VALUES (1, 1, ?, ?, 0, 100), (2, 1, ?, NULL, 1, 200), (3, 2, ?, ?, 0, 300), (4, 1, ?, ?, 0, 400)',
			'anna', 'first', 'bob', 'mod', 'staff', 'anna', 'third');

		$this->posting = new Posting($db);
	}

	public function testATopicCarriesItsForumAndWhetherTheMemberIsSubscribed(): void {
		$topic = $this->posting->topic(1, 3, 5);

		$this->assertNotNull($topic);
		$this->assertSame(array(1, 'Open', '', null, null, 1, 'Hello', false, true),
			array($topic->forumId(), $topic->forumName(), $topic->redirectUrl(), $topic->groupPostsReplies(), $topic->groupPostsTopics(), $topic->topicId(), $topic->subject(), $topic->topicClosed(), $topic->subscribed()));
		$this->assertSame(array(array(7, 'mod')), array_map(static fn ($moderator): array => array($moderator->userId(), $moderator->username()), $topic->moderators()));

		$this->assertFalse($this->posting->topic(1, 3, 6)?->subscribed());
		$this->assertSame(array(false, true, true), array($this->posting->topic(2, 4, 5)?->groupPostsReplies(), $this->posting->topic(2, 4, 5)?->groupPostsTopics(), $this->posting->topic(2, 4, 5)?->topicClosed()));
		$this->assertNull($this->posting->topic(2, 3, 5), 'a forum the group may not read');
		$this->assertNull($this->posting->topic(9, 3, 5));
	}

	public function testAForumCarriesWhereItRedirects(): void {
		$forum = $this->posting->forum(3, 3);

		$this->assertNotNull($forum);
		$this->assertSame(array(3, 'Away', 'http://example.com/', 0, '', false), array($forum->forumId(), $forum->forumName(), $forum->redirectUrl(), $forum->topicId(), $forum->subject(), $forum->subscribed()));
		$this->assertSame(array(), $forum->moderators());
		$this->assertNull($this->posting->forum(2, 3));
		$this->assertSame(array(false, true), array($this->posting->forum(2, 4)?->groupPostsReplies(), $this->posting->forum(2, 4)?->groupPostsTopics()));
	}

	public function testAQuoteIsAPostOfTheTopicRepliedTo(): void {
		$this->assertSame(array('bob', ''), array($this->posting->quote(2, 1)?->poster(), $this->posting->quote(2, 1)?->message()));
		$this->assertNull($this->posting->quote(3, 1));
	}

	public function testTheReviewIsTheNewestPostsNewestFirst(): void {
		$this->assertSame(3, $this->posting->reviewCount(1));
		$this->assertSame(array('4|anna|third|0|400', '2|bob||1|200'), array_map(static fn ($post): string => implode('|', array($post->id(), $post->poster(), $post->message(), (int) $post->hidesSmilies(), $post->postedAt())),
			$this->posting->review(1, 2)));
	}
}
