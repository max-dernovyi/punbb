<?php
/**
 * The rebuild's repository over an in-memory SQLite database with the forum's
 * tables: the first post, a batch of posts with their topics, and the post a
 * next cycle starts at.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Reindex\Api\Data\IndexablePostInterface;
use PunBB\Module\Reindex\Model\IndexablePosts;

class IndexablePostsTest extends TestCase {
	private Connection $db;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');
		$this->db->execute('CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, subject VARCHAR(255) NOT NULL, first_post_id INTEGER NOT NULL)');
		$this->db->execute('CREATE TABLE pun_posts (id INTEGER PRIMARY KEY, topic_id INTEGER NOT NULL, message TEXT)');
	}

	public function testAnEmptyBoardHasNoPostToStartAt(): void {
		$posts = new IndexablePosts($this->db);

		$this->assertNull($posts->firstId());
		$this->assertSame(array(), $posts->batch(1, 10));
		$this->assertNull($posts->nextId(0));
	}

	public function testABatchWalksThePostsInTheOrderOfTheirIds(): void {
		$this->db->execute('INSERT INTO pun_topics (id, subject, first_post_id) VALUES (1, ?, 3), (2, ?, 5)', 'One', 'Two');
		$this->db->execute('INSERT INTO pun_posts (id, topic_id, message) VALUES (5, 2, ?), (3, 1, ?), (4, 1, NULL), (8, 2, ?)', 'opens two', 'opens one', 'reply');

		$posts = new IndexablePosts($this->db);
		$describe = static fn (IndexablePostInterface $post): string => $post->id().' '.$post->topicId().' '.$post->subject().' '.($post->isTopic() ? 'topic' : 'reply').' ['.$post->message().']';

		$this->assertSame(3, $posts->firstId());
		$this->assertSame(array('3 1 One topic [opens one]', '4 1 One reply []'), array_map($describe, $posts->batch(1, 2)));
		$this->assertSame(array('5 2 Two topic [opens two]', '8 2 Two reply [reply]'), array_map($describe, $posts->batch(5, 10)));
		$this->assertSame(5, $posts->nextId(4));
		$this->assertSame(8, $posts->nextId(5));
		$this->assertNull($posts->nextId(8));
	}
}
