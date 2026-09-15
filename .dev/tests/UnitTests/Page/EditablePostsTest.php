<?php
/**
 * The edit page's repository over an in-memory SQLite database with the
 * forum's tables: a post with its topic and forum as a group may read it, the
 * subject of a topic and the topics moved away from it, and a post's message
 * with who edited it, unless the edit is silent.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Edit\Model\EditablePosts;
use PunBB\Module\Edit\Model\PostEdit;

class EditablePostsTest extends TestCase {
	private Connection $db;

	private EditablePosts $posts;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');

		foreach (array(
			'CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80) NOT NULL, moderators TEXT)',
			'CREATE TABLE pun_forum_perms (group_id INTEGER NOT NULL, forum_id INTEGER NOT NULL, read_forum INTEGER NOT NULL)',
			'CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, forum_id INTEGER NOT NULL, subject VARCHAR(255) NOT NULL, first_post_id INTEGER NOT NULL, closed INTEGER NOT NULL DEFAULT 0, moved_to INTEGER)',
			'CREATE TABLE pun_posts (id INTEGER PRIMARY KEY, topic_id INTEGER NOT NULL, poster VARCHAR(200) NOT NULL, poster_id INTEGER NOT NULL, message TEXT, hide_smilies INTEGER NOT NULL DEFAULT 0, edited INTEGER, edited_by VARCHAR(200))',
		) as $table)
			$this->db->execute($table);

		$this->db->execute('INSERT INTO pun_forums (id, forum_name, moderators) VALUES (1, ?, ?), (2, ?, NULL)', 'Open', serialize(array('mod' => 7)), 'Staff');
		$this->db->execute('INSERT INTO pun_forum_perms (group_id, forum_id, read_forum) VALUES (3, 2, 0)');
		$this->db->execute('INSERT INTO pun_topics (id, forum_id, subject, first_post_id, closed, moved_to) VALUES (1, 1, ?, 1, 0, NULL), (2, 2, ?, 3, 1, NULL), (3, 2, ?, 0, 0, 1)', 'Hello', 'Staff room', 'Hello');
		$this->db->execute('INSERT INTO pun_posts (id, topic_id, poster, poster_id, message, hide_smilies, edited, edited_by) VALUES (1, 1, ?, 3, ?, 0, 50, ?), (2, 1, ?, 4, NULL, 1, NULL, NULL), (3, 2, ?, 7, ?, 0, NULL, NULL)',
			'anna', 'first', 'anna', 'bob', 'mod', 'staff');

		$this->posts = new EditablePosts($this->db);
	}

	public function testAPostCarriesItsTopicAndForumAsTheGroupMayReadIt(): void {
		$post = $this->posts->find(2, 1);

		$this->assertNotNull($post);
		$this->assertSame(array(2, 1, 'Open', 1, 'Hello', 1, false, false, 'bob', 4, '', true),
			array($post->id(), $post->forumId(), $post->forumName(), $post->topicId(), $post->subject(), $post->firstPostId(), $post->isTopic(), $post->topicClosed(),
				$post->poster(), $post->posterId(), $post->message(), $post->hidesSmilies()));
		$this->assertSame(array(array(7, 'mod')), array_map(static fn ($moderator): array => array($moderator->userId(), $moderator->username()), $post->moderators()));

		$this->assertTrue($this->posts->find(3, 1)?->isTopic());
		$this->assertTrue($this->posts->find(3, 1)?->topicClosed());
		$this->assertNull($this->posts->find(3, 3));
		$this->assertNull($this->posts->find(9, 1));
	}

	public function testATopicIsRenamedWithTheTopicsMovedAwayFromIt(): void {
		$this->posts->renameTopic(new PostEdit(1, 1, 'Renamed', 'x', false, null, null), new PostEdit(3, 2, null, 'x', false, null, null));

		$this->assertSame(array('Renamed', 'Staff room', 'Renamed'), array_map(static fn ($row): string => $row->string('subject'), $this->db->select('SELECT subject FROM pun_topics ORDER BY id')));
	}

	public function testAMessageIsStoredWithWhoEditedItUnlessTheEditIsSilent(): void {
		$this->posts->saveMessage(new PostEdit(1, 1, null, 'changed', true, null, null), new PostEdit(2, 1, null, 'second', false, 900, 'mod'));

		$this->assertSame(array('changed|1|50|anna', 'second|0|900|mod', 'staff|0||'), array_map(static fn ($row): string => implode('|', $row->values()),
			$this->db->select('SELECT message, hide_smilies, edited, edited_by FROM pun_posts ORDER BY id')));
	}
}
