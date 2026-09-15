<?php
/**
 * A forum page's repository over an in-memory SQLite database with the
 * forum's tables: a forum as a group reads it and a member subscribes to it,
 * a page of its topic ids in their order, and the topics with who posted.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Viewforum\Api\Data\ListedTopicInterface;
use PunBB\Module\Viewforum\Model\ForumTopics;

class ForumTopicsTest extends TestCase {
	private ForumTopics $forums;

	protected function setUp(): void {
		$db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');

		foreach (array(
			'CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80) NOT NULL, forum_desc TEXT, redirect_url VARCHAR(100), moderators TEXT, num_topics INTEGER NOT NULL DEFAULT 0, sort_by INTEGER NOT NULL DEFAULT 0)',
			'CREATE TABLE pun_forum_perms (group_id INTEGER NOT NULL, forum_id INTEGER NOT NULL, read_forum INTEGER NOT NULL, post_topics INTEGER NOT NULL)',
			'CREATE TABLE pun_forum_subscriptions (user_id INTEGER NOT NULL, forum_id INTEGER NOT NULL)',
			'CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, forum_id INTEGER NOT NULL, poster VARCHAR(200) NOT NULL, subject VARCHAR(255) NOT NULL, posted INTEGER NOT NULL, first_post_id INTEGER NOT NULL, last_post INTEGER NOT NULL, last_post_id INTEGER NOT NULL, last_poster VARCHAR(200), num_views INTEGER NOT NULL DEFAULT 0, num_replies INTEGER NOT NULL DEFAULT 0, closed INTEGER NOT NULL DEFAULT 0, sticky INTEGER NOT NULL DEFAULT 0, moved_to INTEGER)',
			'CREATE TABLE pun_posts (id INTEGER PRIMARY KEY, topic_id INTEGER NOT NULL, poster_id INTEGER NOT NULL)',
		) as $table)
			$db->execute($table);

		$db->execute('INSERT INTO pun_forums (id, forum_name, forum_desc, redirect_url, moderators, num_topics, sort_by) VALUES (1, ?, ?, NULL, ?, 4, 0), (2, ?, NULL, ?, NULL, 0, 1)',
			'Open', 'About it', serialize(array('mod' => 7)), 'Away', 'http://example.com/');
		$db->execute('INSERT INTO pun_forum_perms (group_id, forum_id, read_forum, post_topics) VALUES (3, 2, 0, 1), (4, 1, 1, 0)');
		$db->execute('INSERT INTO pun_forum_subscriptions (user_id, forum_id) VALUES (5, 1)');

		foreach (array(
			array(1, 1, 'anna', 'Old', 100, 1, 500, 3, 'bob', 4, 2, 0, 0, null),
			array(2, 1, 'bob', 'Sticky', 50, 4, 60, 4, 'bob', 1, 0, 1, 1, null),
			array(3, 1, 'carl', 'Recent', 300, 5, 400, 5, 'carl', 0, 0, 0, 0, null),
			array(4, 1, 'dan', 'Moved', 350, 6, 350, 6, 'dan', 0, 0, 0, 0, 9),
		) as $topic)
			$db->execute('INSERT INTO pun_topics (id, forum_id, poster, subject, posted, first_post_id, last_post, last_post_id, last_poster, num_views, num_replies, closed, sticky, moved_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', ...$topic);

		$db->execute('INSERT INTO pun_posts (id, topic_id, poster_id) VALUES (1, 1, 5), (2, 1, 6), (3, 1, 5), (5, 3, 6)');

		$this->forums = new ForumTopics($db);
	}

	public function testAForumCarriesWhatItsPageShows(): void {
		$forum = $this->forums->forum(1, 1, 5);

		$this->assertNotNull($forum);
		$this->assertSame(array(1, 'Open', 'About it', '', 4, false, null, true),
			array($forum->id(), $forum->name(), $forum->description(), $forum->redirectUrl(), $forum->topicCount(), $forum->sortsByPosted(), $forum->groupPostsTopics(), $forum->isSubscribed()));
		$this->assertSame(array(array(7, 'mod')), array_map(static fn ($moderator): array => array($moderator->userId(), $moderator->username()), $forum->moderators()));

		$this->assertFalse($this->forums->forum(1, 1, 6)?->isSubscribed());
		$this->assertFalse($this->forums->forum(1, 1, null)?->isSubscribed());
		$this->assertFalse($this->forums->forum(1, 4, null)?->groupPostsTopics());
	}

	public function testAForumTheGroupMayNotReadIsNotThere(): void {
		$this->assertNull($this->forums->forum(2, 3, null));
		$this->assertNull($this->forums->forum(9, 1, null));

		$away = $this->forums->forum(2, 1, 5);
		$this->assertSame(array('http://example.com/', '', true, false), array($away?->redirectUrl(), $away?->description(), $away?->sortsByPosted(), $away?->isSubscribed()));
	}

	public function testTheTopicIdsComeStickyFirstThenNewestAPageAtATime(): void {
		$this->assertSame(array(2, 1, 3, 4), $this->forums->topicIds(1, false, 0, 10));
		$this->assertSame(array(2, 4, 3, 1), $this->forums->topicIds(1, true, 0, 10));
		$this->assertSame(array(4, 3), $this->forums->topicIds(1, true, 1, 2));
		$this->assertSame(array(), $this->forums->topicIds(2, false, 0, 10));
	}

	public function testTheTopicsKeepTheirOrderAndSayWhoPostedInThem(): void {
		$describe = static fn (ListedTopicInterface $topic): string => implode(' ', array($topic->id(), $topic->poster(), $topic->subject(), $topic->posted(), $topic->firstPostId(), $topic->lastPost(),
			$topic->lastPostId(), $topic->lastPoster(), $topic->viewCount(), $topic->replyCount(), (int) $topic->isClosed(), (int) $topic->isSticky(), var_export($topic->movedTo(), true), (int) $topic->hasPosted()));

		$this->assertSame(array('2 bob Sticky 50 4 60 4 bob 1 0 1 1 NULL 0', '1 anna Old 100 1 500 3 bob 4 2 0 0 NULL 1', '4 dan Moved 350 6 350 6 dan 0 0 0 0 9 0'),
			array_map($describe, $this->forums->topics(array(1, 2, 4), false, 5)));

		$this->assertSame(array('1 0', '3 0'), array_map(static fn (ListedTopicInterface $topic): string => $topic->id().' '.(int) $topic->hasPosted(), $this->forums->topics(array(1, 3), false, null)));
		$this->assertSame(array('3 1', '1 1'), array_map(static fn (ListedTopicInterface $topic): string => $topic->id().' '.(int) $topic->hasPosted(), $this->forums->topics(array(1, 3), true, 6)));
		$this->assertSame(array(), $this->forums->topics(array(), false, 5));
	}
}
