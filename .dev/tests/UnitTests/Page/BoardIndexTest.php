<?php
/**
 * The board index's repository over an in-memory SQLite database with the
 * forum's tables: the forums a group may read in display order, the topics
 * with posts since a moment, the board's figures and the visitors online.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Index\Api\Data\ForumInterface;
use PunBB\Module\Index\Model\BoardIndex;

class BoardIndexTest extends TestCase {
	private Connection $db;

	private BoardIndex $board;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');

		foreach (array(
			'CREATE TABLE pun_categories (id INTEGER PRIMARY KEY, cat_name VARCHAR(80) NOT NULL, disp_position INTEGER NOT NULL)',
			'CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80) NOT NULL, forum_desc TEXT, redirect_url VARCHAR(100), moderators TEXT, num_topics INTEGER NOT NULL DEFAULT 0, num_posts INTEGER NOT NULL DEFAULT 0, last_post INTEGER, last_post_id INTEGER, last_poster VARCHAR(200), disp_position INTEGER NOT NULL, cat_id INTEGER NOT NULL)',
			'CREATE TABLE pun_forum_perms (group_id INTEGER NOT NULL, forum_id INTEGER NOT NULL, read_forum INTEGER NOT NULL)',
			'CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, forum_id INTEGER NOT NULL, last_post INTEGER NOT NULL, moved_to INTEGER)',
			'CREATE TABLE pun_users (id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL, username VARCHAR(200) NOT NULL, registered INTEGER NOT NULL)',
			'CREATE TABLE pun_online (user_id INTEGER NOT NULL, ident VARCHAR(200) NOT NULL, idle INTEGER NOT NULL)',
		) as $table)
			$this->db->execute($table);

		foreach (array(array(1, 'Second by position', 2), array(2, 'First by position', 1)) as $category)
			$this->db->execute('INSERT INTO pun_categories (id, cat_name, disp_position) VALUES (?, ?, ?)', ...$category);

		foreach (array(
			array(1, 'News', null, null, serialize(array('mod' => 4, '12' => 5)), 2, 3, 900, 31, 'anna', 2, 1),
			array(2, 'Hidden', 'Staff only', null, null, 1, 1, 800, 21, 'bob', 1, 1),
			array(3, 'Elsewhere', null, 'http://example.com/', null, 0, 0, null, null, null, 1, 2),
			array(4, 'Open', '<b>desc</b>', null, 'not serialized', 1, 5, 700, 41, 'zoë', 3, 1),
		) as $forum)
			$this->db->execute('INSERT INTO pun_forums (id, forum_name, forum_desc, redirect_url, moderators, num_topics, num_posts, last_post, last_post_id, last_poster, disp_position, cat_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', ...$forum);

		// Members may not read forum 2; guests may read forum 4 explicitly
		$this->db->execute('INSERT INTO pun_forum_perms (group_id, forum_id, read_forum) VALUES (3, 2, 0), (2, 4, 1)');

		foreach (array(array(11, 1, 900, null), array(12, 1, 500, null), array(21, 2, 800, null), array(41, 4, 700, null), array(42, 4, 950, 11)) as $topic)
			$this->db->execute('INSERT INTO pun_topics (id, forum_id, last_post, moved_to) VALUES (?, ?, ?, ?)', ...$topic);

		$this->board = new BoardIndex($this->db);
	}

	/** @param list<ForumInterface> $forums */
	private static function names(array $forums): array {
		return array_map(static fn (ForumInterface $forum): string => $forum->name(), $forums);
	}

	public function testAGroupGetsTheForumsItMayReadByCategoryAndPosition(): void {
		$this->assertSame(array('Elsewhere', 'Hidden', 'News', 'Open'), self::names($this->board->forums(1)));
		$this->assertSame(array('Elsewhere', 'News', 'Open'), self::names($this->board->forums(3)));
	}

	public function testAForumCarriesItsCategoryItsFiguresAndItsLastPost(): void {
		[$elsewhere, $news, $open] = $this->board->forums(3);

		$this->assertSame(array(2, 'First by position', 3, 'Elsewhere', '', 'http://example.com/', 0, 0, null, null, null),
			array($elsewhere->categoryId(), $elsewhere->categoryName(), $elsewhere->id(), $elsewhere->name(), $elsewhere->description(), $elsewhere->redirectUrl(),
				$elsewhere->topicCount(), $elsewhere->postCount(), $elsewhere->lastPost(), $elsewhere->lastPostId(), $elsewhere->lastPoster()));
		$this->assertSame(array(1, 'Second by position', '', 2, 3, 900, 31, 'anna'), array($news->categoryId(), $news->categoryName(), $news->redirectUrl(), $news->topicCount(), $news->postCount(), $news->lastPost(), $news->lastPostId(), $news->lastPoster()));
		$this->assertSame('<b>desc</b>', $open->description());
	}

	public function testAForumListsItsModeratorsAndNoneWhenItStoresThemBroken(): void {
		[, $news, $open] = $this->board->forums(3);

		$this->assertSame(array(array(4, 'mod'), array(5, '12')), array_map(static fn ($moderator): array => array($moderator->userId(), $moderator->username()), $news->moderators()));
		$this->assertSame(array(), $open->moderators());
	}

	public function testTheActiveTopicsAreThoseAGroupMayReadWithAPostSinceTheMomentAndNotMoved(): void {
		$topics = array_map(static fn ($topic): array => array($topic->forumId(), $topic->topicId(), $topic->lastPost()), $this->board->activeTopics(3, 600));

		$this->assertSame(array(array(1, 11, 900), array(4, 41, 700)), $topics);
		$this->assertCount(3, $this->board->activeTopics(1, 600));
	}

	public function testTheStatisticsCountTheMembersWhoConfirmedTheirAddressAndTheForumsFigures(): void {
		foreach (array(array(1, 2, 'Guest', 0), array(2, 1, 'admin', 100), array(3, 0, 'pending', 900), array(4, 3, 'newest', 800)) as $user)
			$this->db->execute('INSERT INTO pun_users (id, group_id, username, registered) VALUES (?, ?, ?, ?)', ...$user);

		$statistics = $this->board->statistics();

		$this->assertSame(array(2, 4, 'newest', 4, 9), array($statistics->userCount(), $statistics->newestUserId(), $statistics->newestUsername(), $statistics->topicCount(), $statistics->postCount()));
	}

	public function testTheVisitorsOnlineAreThoseNotIdleByName(): void {
		foreach (array(array(1, '192.0.2.1', 0), array(5, 'bob', 0), array(4, 'anna', 0), array(6, 'idle', 1)) as $visitor)
			$this->db->execute('INSERT INTO pun_online (user_id, ident, idle) VALUES (?, ?, ?)', ...$visitor);

		$online = array_map(static fn ($visitor): array => array($visitor->userId(), $visitor->ident(), $visitor->isGuest()), $this->board->onlineVisitors());

		$this->assertSame(array(array(1, '192.0.2.1', true), array(4, 'anna', false), array(5, 'bob', false)), $online);
	}
}
