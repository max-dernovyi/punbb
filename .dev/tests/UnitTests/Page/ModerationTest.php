<?php
/**
 * The moderation's repositories over an in-memory SQLite database with the
 * forum's tables: a forum, its topics and a topic's posts as a moderator reads
 * them, and deleting, splitting, moving, merging, deleting, closing and
 * sticking them.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Moderate\Api\Data\ListedTopicInterface;
use PunBB\Module\Moderate\Model\ModeratedPosts;
use PunBB\Module\Moderate\Model\ModeratedTopics;
use PunBB\Module\Moderate\Model\NewTopic;
use PunBB\Module\Moderate\Model\RedirectTopic;

class ModerationTest extends TestCase {
	private Connection $db;

	private ModeratedPosts $posts;

	private ModeratedTopics $topics;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');
		$this->db->execute('CREATE TABLE pun_categories (id INTEGER PRIMARY KEY, cat_name VARCHAR(80) NOT NULL, disp_position INTEGER NOT NULL DEFAULT 0)');
		$this->db->execute('CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80) NOT NULL, redirect_url VARCHAR(100), moderators TEXT, num_topics INTEGER NOT NULL DEFAULT 0, sort_by INTEGER NOT NULL DEFAULT 0, disp_position INTEGER NOT NULL DEFAULT 0, cat_id INTEGER NOT NULL)');
		$this->db->execute('CREATE TABLE pun_forum_perms (group_id INTEGER NOT NULL, forum_id INTEGER NOT NULL, read_forum INTEGER NOT NULL DEFAULT 1)');
		$this->db->execute('CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, poster VARCHAR(200) NOT NULL, subject VARCHAR(255) NOT NULL, posted INTEGER NOT NULL DEFAULT 0, first_post_id INTEGER NOT NULL DEFAULT 0, last_post INTEGER NOT NULL DEFAULT 0, last_post_id INTEGER NOT NULL DEFAULT 0, last_poster VARCHAR(200), num_views INTEGER NOT NULL DEFAULT 0, num_replies INTEGER NOT NULL DEFAULT 0, closed INTEGER NOT NULL DEFAULT 0, sticky INTEGER NOT NULL DEFAULT 0, moved_to INTEGER, forum_id INTEGER NOT NULL DEFAULT 0)');
		$this->db->execute('CREATE TABLE pun_posts (id INTEGER PRIMARY KEY, poster VARCHAR(200) NOT NULL, poster_id INTEGER NOT NULL DEFAULT 1, poster_ip VARCHAR(39), message TEXT, hide_smilies INTEGER NOT NULL DEFAULT 0, posted INTEGER NOT NULL DEFAULT 0, edited INTEGER, edited_by VARCHAR(200), topic_id INTEGER NOT NULL DEFAULT 0)');
		$this->db->execute('CREATE TABLE pun_users (id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL, title VARCHAR(50), num_posts INTEGER NOT NULL DEFAULT 0)');
		$this->db->execute('CREATE TABLE pun_groups (g_id INTEGER PRIMARY KEY, g_user_title VARCHAR(50))');
		$this->db->execute('CREATE TABLE pun_subscriptions (user_id INTEGER NOT NULL, topic_id INTEGER NOT NULL)');

		$this->db->execute('INSERT INTO pun_categories (id, cat_name, disp_position) VALUES (1, ?, 2), (2, ?, 1)', 'Second category', 'First category');
		$this->db->execute('INSERT INTO pun_forums (id, forum_name, redirect_url, moderators, num_topics, sort_by, disp_position, cat_id) VALUES (1, ?, NULL, ?, 3, 0, 1, 2), (2, ?, NULL, NULL, 1, 1, 2, 2), (3, ?, ?, NULL, 0, 0, 1, 1), (4, ?, NULL, NULL, 0, 0, 2, 1)',
			'Lounge', serialize(array('mod' => 4, 'other' => '7')), 'Hidden', 'Elsewhere', 'http://example.com/', 'Archive');
		$this->db->execute('INSERT INTO pun_forum_perms (group_id, forum_id, read_forum) VALUES (3, 2, 0)');
		$this->db->execute('INSERT INTO pun_topics (id, poster, subject, posted, first_post_id, last_post, last_post_id, last_poster, num_views, num_replies, closed, sticky, moved_to, forum_id) VALUES'.
			' (1, ?, ?, 250, 1, 300, 3, ?, 5, 2, 0, 0, NULL, 1), (2, ?, ?, 400, 4, 400, 4, ?, 1, 0, 1, 1, NULL, 1), (3, ?, ?, 50, 0, 500, 0, NULL, 0, 0, 0, 0, 1, 2), (4, ?, ?, 200, 5, 600, 5, ?, 0, 0, 0, 0, NULL, 1)',
			'member', 'First topic', 'mod', 'mod', 'Sticky topic', 'mod', 'member', 'Moved topic', 'member', 'Latest topic', 'member');
		$this->db->execute('INSERT INTO pun_posts (id, poster, poster_id, poster_ip, message, hide_smilies, posted, edited, edited_by, topic_id) VALUES'.
			' (1, ?, 3, ?, ?, 0, 100, NULL, NULL, 1), (2, ?, 4, ?, ?, 1, 200, 250, ?, 1), (3, ?, 1, NULL, NULL, 0, 300, NULL, NULL, 1), (4, ?, 4, ?, ?, 0, 400, NULL, NULL, 2), (5, ?, 3, ?, ?, 0, 600, NULL, NULL, 4)',
			'member', '192.0.2.3', 'Hello', 'mod', '192.0.2.4', 'Reply', 'mod', 'Visitor', 'mod', '192.0.2.4', 'Sticky', 'member', '192.0.2.3', 'Latest');
		$this->db->execute('INSERT INTO pun_users (id, group_id, title, num_posts) VALUES (1, 2, NULL, 0), (3, 3, ?, 2), (4, 4, NULL, 2)', 'Veteran');
		$this->db->execute('INSERT INTO pun_groups (g_id, g_user_title) VALUES (2, NULL), (3, NULL), (4, ?)', 'Moderator');
		$this->db->execute('INSERT INTO pun_subscriptions (user_id, topic_id) VALUES (3, 1), (3, 2), (4, 4)');

		$this->posts = new ModeratedPosts($this->db);
		$this->topics = new ModeratedTopics($this->db);
	}

	/**
	 * @param list<ListedTopicInterface> $topics
	 * @return list<int>
	 */
	private static function ids(array $topics): array {
		return array_map(static fn (ListedTopicInterface $topic): int => $topic->id(), $topics);
	}

	/** @return list<string> */
	private function column(string $sql): array {
		return array_map(static fn ($row): string => implode(' ', array_map(static fn ($value): string => var_export($value, true), $row->values())), $this->db->select($sql));
	}

	public function testAForumIsReadAsTheGroupMayReadIt(): void {
		$forum = $this->topics->forum(1, 3);

		$this->assertNotNull($forum);
		$this->assertSame(array(1, 'Lounge', '', 3, false), array($forum->id(), $forum->name(), $forum->redirectUrl(), $forum->topicCount(), $forum->sortsByPosted()));
		$this->assertSame(array('mod 4', 'other 7'), array_map(static fn ($moderator): string => $moderator->username().' '.$moderator->userId(), $forum->moderators()));
		$this->assertNull($this->topics->forum(2, 3), 'a forum the group may not read');
		$this->assertTrue($this->topics->forum(2, 4)?->sortsByPosted());
		$this->assertSame('http://example.com/', $this->topics->forum(3, 3)?->redirectUrl());
		$this->assertNull($this->topics->forum(9, 3));
	}

	public function testAForumsTopicsComeStickyFirstAPageAtATime(): void {
		$this->assertSame(array(2, 4, 1), self::ids($this->topics->topics(1, false, 0, 10, null)));
		$this->assertSame(array(2, 1, 4), self::ids($this->topics->topics(1, true, 0, 10, null)));
		$this->assertSame(array(4), self::ids($this->topics->topics(1, false, 1, 1, null)));

		$posted = $this->topics->topics(1, false, 0, 10, 4);
		$this->assertSame(array(true, false, true), array_map(static fn (ListedTopicInterface $topic): bool => $topic->hasPosted(), $posted), 'the posts a member wrote in a topic count once');

		$sticky = $posted[0];
		$this->assertSame(array('mod', 'Sticky topic', 400, 400, 4, 'mod', 1, 0, true, true, null), array($sticky->poster(), $sticky->subject(), $sticky->posted(), $sticky->lastPost(), $sticky->lastPostId(), $sticky->lastPoster(), $sticky->viewCount(), $sticky->replyCount(), $sticky->isClosed(), $sticky->isSticky(), $sticky->movedTo()));
		$this->assertSame(array(1, ''), array($this->topics->topics(2, false, 0, 10, null)[0]->movedTo(), $this->topics->topics(2, false, 0, 10, null)[0]->lastPoster()));
	}

	public function testATopicsSubjectIsReadInAnyForumOrInOne(): void {
		$this->assertSame('Moved topic', $this->topics->subject(3));
		$this->assertNull($this->topics->subject(9));
		$this->assertSame('First topic', $this->topics->subjectIn(1, 1));
		$this->assertNull($this->topics->subjectIn(1, 2));
		$this->assertSame('Archive', $this->topics->forumName(4));
		$this->assertNull($this->topics->forumName(9));
	}

	public function testTopicsMoveToTheForumsTheGroupMayReadHere(): void {
		$this->assertSame(array('2 First category 1 Lounge', '1 Second category 4 Archive'), array_map(static fn ($forum): string => $forum->categoryId().' '.$forum->categoryName().' '.$forum->forumId().' '.$forum->forumName(), $this->topics->moveTargets(2, 3)),
			'neither the forum moderated, one on another site nor one the group may not read');

		$this->assertSame(2, $this->topics->countTopics(1, 1, 2, 3));
		$this->assertSame(0, $this->topics->countTopics(1));

		$this->topics->removeRedirects(2, 1, 4);
		$this->topics->moveTopics(4, 1, 2);
		$moved = $this->topics->movedTopic(1);
		$this->assertSame(array('member', 'First topic', 250, 300), array($moved?->poster(), $moved?->subject(), $moved?->posted(), $moved?->lastPost()));
		$this->topics->addRedirects(new RedirectTopic('member', 'First topic', 100, 300, 1, 1));

		$this->assertSame(array("1 4 NULL", "2 4 NULL", "4 1 NULL", "5 1 1"), $this->column('SELECT id, forum_id, moved_to FROM pun_topics ORDER BY id'));
		$this->assertNull($this->topics->movedTopic(9));
	}

	public function testTopicsMergeIntoTheOldestOfThem(): void {
		$this->db->execute('INSERT INTO pun_topics (id, poster, subject, moved_to, forum_id) VALUES (6, ?, ?, 2, 4)', 'mod', 'Redirect to sticky');

		$target = $this->topics->mergeTarget(1, 4, 2, 3);
		$this->assertSame(array(2, 2), array($target->topicCount(), $target->lowestId()), 'a redirect is not merged');
		$this->assertNull($this->topics->mergeTarget(4, 1)->lowestId());

		$this->topics->redirectMerged(2, true, 2, 4);
		$this->topics->mergePosts(2, 2, 4);
		$this->topics->removeMergedSubscriptions(2, 2, 4);

		$this->assertSame(array("2 NULL", "4 2", "6 2"), $this->column('SELECT id, moved_to FROM pun_topics WHERE id IN (2, 4, 6) ORDER BY id'));
		$this->assertSame(array('4 2', '5 2'), $this->column('SELECT id, topic_id FROM pun_posts WHERE id IN (4, 5) ORDER BY id'));
		$this->assertSame(array('3 1', '3 2'), $this->column('SELECT user_id, topic_id FROM pun_subscriptions ORDER BY topic_id'));

		$this->topics->redirectMerged(1, false, 1, 3);
		$this->topics->removeMergedTopics(2, 2, 4);
		$this->assertSame(array("1 NULL", "2 NULL", "3 1", "6 2"), $this->column('SELECT id, moved_to FROM pun_topics ORDER BY id'));
	}

	public function testTopicsAreDeletedWithTheirRedirectsSubscriptionsAndPosts(): void {
		$this->assertSame(array(2), $this->topics->redirectForums(1, 4));
		$this->assertSame(array(1, 2, 3), $this->topics->postIds(1, 3));

		$this->topics->removeTopics(1);
		$this->topics->removeSubscriptions(1, 2);
		$this->topics->removePosts(1);

		$this->assertSame(array("2", "4"), $this->column('SELECT id FROM pun_topics ORDER BY id'));
		$this->assertSame(array('4'), $this->column('SELECT topic_id FROM pun_subscriptions'));
		$this->assertSame(array("4", "5"), $this->column('SELECT id FROM pun_posts ORDER BY id'));
		$this->assertSame(array(), $this->topics->redirectForums());
	}

	public function testTopicsCloseAndStickWhereTheForumHoldsThem(): void {
		$this->topics->closeTopics(true, 1, 1, 3, 4);
		$this->topics->closeTopics(false, 1, 2);
		$this->topics->stickTopics(true, 1, 4);
		$this->topics->stickTopics(false, 2, 2);

		$this->assertSame(array('1 1 0', '2 0 1', '3 0 0', '4 1 1'), $this->column('SELECT id, closed, sticky FROM pun_topics ORDER BY id'));
	}

	public function testATopicsPostsAreReadWithTheirPosters(): void {
		$this->assertSame('192.0.2.4', $this->posts->posterAddress(2));
		$this->assertNull($this->posts->posterAddress(3), 'a post that recorded no address');
		$this->assertNull($this->posts->posterAddress(9));

		$topic = $this->posts->topic(1, 1);
		$this->assertSame(array(1, 'First topic', 'member', 1, 250, 2), array($topic?->id(), $topic?->subject(), $topic?->poster(), $topic?->firstPostId(), $topic?->posted(), $topic?->replyCount()));
		$this->assertNull($this->posts->topic(1, 2), 'a topic in a forum the moderator was not checked for');
		$this->assertNull($this->posts->topic(3, 2), 'a redirect');

		$posts = $this->posts->posts(1, 0, 2);
		$this->assertSame(array(1, 2), array_map(static fn ($post): int => $post->id(), $posts));
		$this->assertSame(array('member', 3, 'Hello', false, 100, null, '', 'Veteran', 2, 3, null), array($posts[0]->poster(), $posts[0]->posterId(), $posts[0]->message(), $posts[0]->hidesSmilies(), $posts[0]->posted(), $posts[0]->edited(), $posts[0]->editedBy(), $posts[0]->posterTitle(), $posts[0]->posterPostCount(), $posts[0]->posterGroupId(), $posts[0]->posterGroupTitle()));
		$this->assertSame(array(true, 250, 'mod', '', 'Moderator'), array($posts[1]->hidesSmilies(), $posts[1]->edited(), $posts[1]->editedBy(), $posts[1]->posterTitle(), $posts[1]->posterGroupTitle()));
		$this->assertSame(array(3), array_map(static fn ($post): int => $post->id(), $this->posts->posts(1, 2, 2)));
		$this->assertSame('', $this->posts->posts(1, 2, 2)[0]->message(), 'a post stored without a message');
	}

	public function testRepliesAreDeletedOrSplitOffIntoATopicOfTheirOwn(): void {
		$this->assertSame(2, $this->posts->countReplies(1, 1, 1, 2, 3, 4));
		$this->assertSame(0, $this->posts->countReplies(1, 1));

		$first = $this->posts->firstPost(2);
		$this->assertSame(array(2, 'mod', 200), array($first?->id(), $first?->poster(), $first?->posted()));
		$this->assertNull($this->posts->firstPost(9));

		$this->posts->addTopics(new NewTopic('mod', 'Split \'off\'', 200, 2, 1));
		$new = $this->posts->lastTopicId();
		$this->posts->movePosts($new, 2, 3);
		$this->posts->deletePosts(5);

		$this->assertSame(5, $new);
		$this->assertSame(array("'mod' 'Split \\'off\\'' 200 2 1"), $this->column('SELECT poster, subject, posted, first_post_id, forum_id FROM pun_topics WHERE id=5'));
		$this->assertSame(array('1 1', '2 5', '3 5', '4 2'), $this->column('SELECT id, topic_id FROM pun_posts ORDER BY id'));
	}
}
