<?php
/**
 * The topic page's repository over an in-memory SQLite database with the
 * forum's tables: where a post is and how many posts come before it, the first
 * post after a moment and the last post, the topic as a group reads it, a page
 * of its posts with their posters, and counting a view.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Viewtopic\Api\Data\TopicPostInterface;
use PunBB\Module\Viewtopic\Model\TopicPosts;

class TopicPostsTest extends TestCase {
	private Connection $db;

	private TopicPosts $topics;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');

		foreach (array(
			'CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80) NOT NULL, moderators TEXT)',
			'CREATE TABLE pun_forum_perms (group_id INTEGER NOT NULL, forum_id INTEGER NOT NULL, read_forum INTEGER NOT NULL, post_replies INTEGER NOT NULL)',
			'CREATE TABLE pun_subscriptions (user_id INTEGER NOT NULL, topic_id INTEGER NOT NULL)',
			'CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, forum_id INTEGER NOT NULL, subject VARCHAR(255) NOT NULL, first_post_id INTEGER NOT NULL, last_post_id INTEGER, closed INTEGER NOT NULL DEFAULT 0, sticky INTEGER NOT NULL DEFAULT 0, num_replies INTEGER NOT NULL DEFAULT 0, num_views INTEGER NOT NULL DEFAULT 0, moved_to INTEGER)',
			'CREATE TABLE pun_posts (id INTEGER PRIMARY KEY, topic_id INTEGER NOT NULL, poster VARCHAR(200) NOT NULL, poster_id INTEGER NOT NULL, poster_ip VARCHAR(39), poster_email VARCHAR(80), message TEXT, hide_smilies INTEGER NOT NULL DEFAULT 0, posted INTEGER NOT NULL, edited INTEGER, edited_by VARCHAR(200))',
			'CREATE TABLE pun_users (id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL, email VARCHAR(80) NOT NULL, title VARCHAR(50), url VARCHAR(100), location VARCHAR(30), signature TEXT, email_setting INTEGER NOT NULL DEFAULT 1, num_posts INTEGER NOT NULL DEFAULT 0, registered INTEGER NOT NULL DEFAULT 0, admin_note VARCHAR(30), avatar INTEGER NOT NULL DEFAULT 0, avatar_width INTEGER NOT NULL DEFAULT 0, avatar_height INTEGER NOT NULL DEFAULT 0)',
			'CREATE TABLE pun_groups (g_id INTEGER PRIMARY KEY, g_user_title VARCHAR(50))',
			'CREATE TABLE pun_online (user_id INTEGER NOT NULL, idle INTEGER NOT NULL DEFAULT 0)',
		) as $table)
			$this->db->execute($table);

		$this->db->execute('INSERT INTO pun_forums (id, forum_name, moderators) VALUES (1, ?, ?), (2, ?, NULL)', 'Open', serialize(array('mod' => 7)), 'Staff');
		$this->db->execute('INSERT INTO pun_forum_perms (group_id, forum_id, read_forum, post_replies) VALUES (3, 2, 0, 0), (4, 1, 1, 0)');
		$this->db->execute('INSERT INTO pun_topics (id, forum_id, subject, first_post_id, last_post_id, closed, sticky, num_replies, moved_to) VALUES (1, 1, ?, 1, 3, 1, 1, 2, NULL), (2, 2, ?, 4, 4, 0, 0, 0, NULL), (3, 1, ?, 1, 3, 0, 0, 0, 1)',
			'Hello', 'Staff room', 'Hello');
		$this->db->execute('INSERT INTO pun_subscriptions (user_id, topic_id) VALUES (3, 1)');
		$this->db->execute('INSERT INTO pun_groups (g_id, g_user_title) VALUES (2, ?), (3, NULL)', 'Guest title');
		$this->db->execute('INSERT INTO pun_users (id, group_id, email, title, url, location, signature, email_setting, num_posts, registered, admin_note, avatar, avatar_width, avatar_height) VALUES'.
			' (1, 2, ?, NULL, NULL, NULL, NULL, 1, 0, 0, NULL, 0, 0, 0), (3, 3, ?, ?, ?, ?, ?, 0, 12, 500, ?, 3, 60, 40)',
			'guest@example.com', 'anna@example.com', 'Chief', 'http://example.com/', 'Köln', 'Sig', 'Watch');
		$this->db->execute('INSERT INTO pun_online (user_id, idle) VALUES (1, 0), (3, 0)');

		foreach (array(array(1, 1, 'anna', 3, '192.0.2.3', null, 'first', 0, 100, null, null), array(2, 1, 'visitor', 1, '192.0.2.9', 'v@example.com', 'second', 1, 200, 250, 'anna'),
			array(3, 1, 'anna', 3, '192.0.2.3', null, null, 0, 200, null, null), array(4, 2, 'anna', 3, '192.0.2.3', null, 'staff', 0, 400, null, null)) as $post)
			$this->db->execute('INSERT INTO pun_posts (id, topic_id, poster, poster_id, poster_ip, poster_email, message, hide_smilies, posted, edited, edited_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', ...$post);

		$this->topics = new TopicPosts($this->db);
	}

	public function testAPostIsLocatedAndTheFirstAndLastPostsAreFound(): void {
		$location = $this->topics->locate(3);

		$this->assertSame(array(1, 200), array($location?->topicId(), $location?->posted()));
		$this->assertNull($this->topics->locate(9));
		$this->assertSame(1, $this->topics->countBefore(1, 200));
		$this->assertSame(2, $this->topics->firstPostAfter(1, 100));
		$this->assertNull($this->topics->firstPostAfter(1, 200));
		$this->assertSame(3, $this->topics->lastPostId(1));
		$this->assertNull($this->topics->lastPostId(9));
	}

	public function testATopicCarriesItsForumAsTheGroupReadsIt(): void {
		$topic = $this->topics->topic(1, 3, 3);

		$this->assertNotNull($topic);
		$this->assertSame(array(1, 'Hello', 1, true, true, 2, 1, 'Open', null, true),
			array($topic->id(), $topic->subject(), $topic->firstPostId(), $topic->isClosed(), $topic->isSticky(), $topic->replyCount(), $topic->forumId(), $topic->forumName(), $topic->groupPostsReplies(), $topic->isSubscribed()));
		$this->assertSame(array(array(7, 'mod')), array_map(static fn ($moderator): array => array($moderator->userId(), $moderator->username()), $topic->moderators()));

		$this->assertFalse($this->topics->topic(1, 4, null)?->groupPostsReplies());
		$this->assertFalse($this->topics->topic(1, 4, null)?->isSubscribed());
		$this->assertNull($this->topics->topic(2, 3, null), 'a forum the group may not read');
		$this->assertNull($this->topics->topic(3, 1, null), 'a topic that only points at another');
	}

	public function testAPageOfPostsComesWithTheirPosters(): void {
		$this->assertSame(array(2, 3), $this->topics->postIds(1, 1, 5));

		$posts = $this->topics->posts(array(3, 2));
		$this->assertSame(array(2, 3), array_map(static fn (TopicPostInterface $post): int => $post->id(), $posts));

		[$guest, $member] = $posts;
		$this->assertSame(array(1, 'visitor', '192.0.2.9', 'v@example.com', 'second', true, 200, 250, 'anna', 'guest@example.com', 2, 'Guest title', false),
			array($guest->posterId(), $guest->poster(), $guest->posterIp(), $guest->posterEmail(), $guest->message(), $guest->hidesSmilies(), $guest->posted(), $guest->edited(), $guest->editedBy(),
				$guest->email(), $guest->groupId(), $guest->groupTitle(), $guest->isOnline()));
		$this->assertSame(array('', null, null, 'Chief', 'http://example.com/', 'Köln', 'Sig', 0, 12, 500, 'Watch', 3, 60, 40, null, true),
			array($member->message(), $member->posterEmail(), $member->edited(), $member->title(), $member->url(), $member->location(), $member->signature(), $member->emailSetting(),
				$member->postCount(), $member->registered(), $member->adminNote(), $member->avatar(), $member->avatarWidth(), $member->avatarHeight(), $member->groupTitle(), $member->isOnline()));

		$this->assertSame(array(), $this->topics->posts(array()));
	}

	public function testAViewIsCounted(): void {
		$this->topics->countView(1, 1);
		$this->topics->countView();

		$this->assertSame(2, (int) $this->db->selectValue('SELECT num_views FROM pun_topics WHERE id=1'));
	}
}
