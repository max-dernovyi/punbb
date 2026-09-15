<?php
/**
 * The repositories of the search page over an in-memory SQLite database with
 * the forum's tables: words and authors matched, the hits a group may read,
 * the searches stored and pruned, the results of a stored search in its sort,
 * each quick search, and the forums the form offers.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Search\Api\Data\ResultForumInterface;
use PunBB\Module\Search\Api\Data\ResultPostInterface;
use PunBB\Module\Search\Api\Data\ResultTopicInterface;
use PunBB\Module\Search\Model\Results;
use PunBB\Module\Search\Model\SearchMark;
use PunBB\Module\Search\Model\Searches;
use PunBB\Module\Search\Model\StoredSearch;

class SearchesTest extends TestCase {
	private Connection $db;

	private Searches $searches;

	private Results $results;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');

		foreach (array(
			'CREATE TABLE pun_users (id INTEGER PRIMARY KEY, username VARCHAR(200) NOT NULL, last_search INTEGER)',
			'CREATE TABLE pun_online (user_id INTEGER NOT NULL, ident VARCHAR(200) NOT NULL, last_search INTEGER)',
			'CREATE TABLE pun_search_words (id INTEGER PRIMARY KEY, word VARCHAR(20) NOT NULL)',
			'CREATE TABLE pun_search_matches (post_id INTEGER NOT NULL, word_id INTEGER NOT NULL, subject_match INTEGER NOT NULL)',
			'CREATE TABLE pun_search_cache (id INTEGER NOT NULL, ident VARCHAR(200) NOT NULL, search_data TEXT)',
			'CREATE TABLE pun_categories (id INTEGER PRIMARY KEY, cat_name VARCHAR(80) NOT NULL, disp_position INTEGER NOT NULL)',
			'CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80) NOT NULL, forum_desc TEXT, redirect_url VARCHAR(100), moderators TEXT, num_topics INTEGER NOT NULL DEFAULT 0, num_posts INTEGER NOT NULL DEFAULT 0, last_post INTEGER, last_post_id INTEGER, last_poster VARCHAR(200), disp_position INTEGER NOT NULL, cat_id INTEGER NOT NULL)',
			'CREATE TABLE pun_forum_perms (group_id INTEGER NOT NULL, forum_id INTEGER NOT NULL, read_forum INTEGER NOT NULL)',
			'CREATE TABLE pun_forum_subscriptions (user_id INTEGER NOT NULL, forum_id INTEGER NOT NULL)',
			'CREATE TABLE pun_subscriptions (user_id INTEGER NOT NULL, topic_id INTEGER NOT NULL)',
			'CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, forum_id INTEGER NOT NULL, poster VARCHAR(200) NOT NULL, subject VARCHAR(255) NOT NULL, posted INTEGER NOT NULL, first_post_id INTEGER NOT NULL, last_post INTEGER NOT NULL, last_post_id INTEGER NOT NULL, last_poster VARCHAR(200), num_replies INTEGER NOT NULL DEFAULT 0, closed INTEGER NOT NULL DEFAULT 0, sticky INTEGER NOT NULL DEFAULT 0, moved_to INTEGER)',
			'CREATE TABLE pun_posts (id INTEGER PRIMARY KEY, topic_id INTEGER NOT NULL, poster VARCHAR(200) NOT NULL, poster_id INTEGER NOT NULL, posted INTEGER NOT NULL, message TEXT, hide_smilies INTEGER NOT NULL DEFAULT 0)',
		) as $table)
			$this->db->execute($table);

		$this->db->execute('INSERT INTO pun_users (id, username) VALUES (1, ?), (2, ?), (3, ?), (4, ?)', 'Guest', 'Anna', 'annabel', 'bob');
		$this->db->execute('INSERT INTO pun_online (user_id, ident) VALUES (1, ?), (2, ?)', '192.0.2.7', 'Anna');
		$this->db->execute('INSERT INTO pun_search_words (id, word) VALUES (1, ?), (2, ?), (3, ?)', 'cat', 'catalogue', 'dog');
		$this->db->execute('INSERT INTO pun_search_matches (post_id, word_id, subject_match) VALUES (1, 1, 0), (1, 1, 1), (2, 2, 0), (3, 3, 1), (4, 1, 0)');
		$this->db->execute('INSERT INTO pun_categories (id, cat_name, disp_position) VALUES (1, ?, 2), (2, ?, 1)', 'Second', 'First');
		$this->db->execute('INSERT INTO pun_forums (id, forum_name, forum_desc, redirect_url, num_topics, num_posts, last_post, last_post_id, last_poster, disp_position, cat_id) VALUES (1, ?, ?, NULL, 2, 3, 500, 3, ?, 1, 1), (2, ?, NULL, NULL, 1, 1, 600, 4, ?, 1, 2), (3, ?, NULL, ?, 0, 0, NULL, NULL, NULL, 2, 2)',
			'Open', 'About', 'bob', 'Hidden', 'Anna', 'Away', 'http://example.com/');
		$this->db->execute('INSERT INTO pun_forum_perms (group_id, forum_id, read_forum) VALUES (4, 2, 0)');
		$this->db->execute('INSERT INTO pun_forum_subscriptions (user_id, forum_id) VALUES (2, 1), (2, 2), (2, 3)');
		$this->db->execute('INSERT INTO pun_subscriptions (user_id, topic_id) VALUES (2, 1), (2, 3)');

		foreach (array(
			array(1, 1, 'Anna', 'Cats', 100, 1, 300, 2, 'annabel', 1, 0, 1, null),
			array(2, 1, 'annabel', 'Dogs', 200, 3, 500, 3, 'bob', 0, 1, 0, null),
			array(3, 2, 'bob', 'Hidden', 250, 4, 600, 4, 'Anna', 0, 0, 0, null),
			array(4, 1, 'bob', 'Moved', 50, 5, 50, 5, 'bob', 0, 0, 0, 2),
		) as $topic)
			$this->db->execute('INSERT INTO pun_topics (id, forum_id, poster, subject, posted, first_post_id, last_post, last_post_id, last_poster, num_replies, closed, sticky, moved_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', ...$topic);

		foreach (array(
			array(1, 1, 'Anna', 2, 100, 'first', 1),
			array(2, 1, 'annabel', 3, 300, 'second', 0),
			array(3, 2, 'annabel', 3, 200, 'third', 0),
			array(4, 3, 'bob', 4, 600, 'hidden', 0),
			array(5, 4, 'bob', 4, 50, 'moved', 0),
		) as $post)
			$this->db->execute('INSERT INTO pun_posts (id, topic_id, poster, poster_id, posted, message, hide_smilies) VALUES (?, ?, ?, ?, ?, ?, ?)', ...$post);

		$this->searches = new Searches($this->db);
		$this->results = new Results($this->db);
	}

	public function testASearchIsMarkedOnTheMemberOrOnTheGuestsVisit(): void {
		$this->searches->markSearched(new SearchMark(3, '192.0.2.9', 700), new SearchMark(null, '192.0.2.7', 800));

		$this->assertSame(700, $this->db->selectValue('SELECT last_search FROM pun_users WHERE id=3'));
		$this->assertSame(800, $this->db->selectValue('SELECT last_search FROM pun_online WHERE ident=?', '192.0.2.7'));
		$this->assertNull($this->db->selectValue('SELECT last_search FROM pun_online WHERE ident=?', 'Anna'));
	}

	public function testWordsMatchByPatternWhereverTheSearchLooks(): void {
		$this->assertSame(array(1, 1, 4), $this->searches->keywordMatches('cat', 0));
		$this->assertSame(array(1, 1, 2, 4), $this->searches->keywordMatches('cat%', 0));
		$this->assertSame(array(1, 4), $this->searches->keywordMatches('cat', 1));
		$this->assertSame(array(1), $this->searches->keywordMatches('cat', -1));
		$this->assertSame(array(), $this->searches->keywordMatches('bird', 0));
	}

	public function testAuthorsMatchByPatternAndTheirPostsAreFound(): void {
		$this->assertSame(array(2, 3), $this->searches->authorIds('anna%'));
		$this->assertSame(array(1, 2, 3), $this->searches->authorPosts(array(3, 2)));
		$this->assertSame(array(), $this->searches->authorPosts(array()));
	}

	public function testTheHitsAreWhatTheGroupMayReadInTheForumsChosen(): void {
		$this->assertSame(array(1, 2, 3, 4), $this->searches->readableHits(array(1, 2, 3, 4), 3, null, true));
		$this->assertSame(array(1, 2, 3), $this->searches->readableHits(array(1, 2, 3, 4), 3, null, false));
		$this->assertSame(array(1, 2, 3), $this->searches->readableHits(array(1, 2, 3, 4), 4, null, true));
		$this->assertSame(array(4), $this->searches->readableHits(array(1, 2, 3, 4), 3, array(2), true));
		$this->assertSame(array(), $this->searches->readableHits(array(), 3, null, true));
	}

	public function testASearchIsStoredForItsSearcherAndTheOfflineOnesArePruned(): void {
		$this->searches->store(new StoredSearch(7, 'Anna', array(3, 1), 2, 'ASC', 'topics'), new StoredSearch(8, 'gone', array(), null, 'DESC', 'posts'));

		$this->assertSame(array('192.0.2.7', 'Anna'), $this->searches->onlineIdents());

		$stored = $this->searches->stored(7, 'Anna');
		$this->assertSame(array(7, 'Anna', array(3, 1), 2, 'ASC', 'topics'), array($stored?->id(), $stored?->ident(), $stored?->resultIds(), $stored?->sortBy(), $stored?->sortDir(), $stored?->showAs()));
		$this->assertNull($this->searches->stored(7, 'bob'), 'a search is read back by its searcher only');
		$this->assertSame(array(), $this->searches->stored(8, 'gone')?->resultIds());
		$this->assertSame('a:4:{s:14:"search_results";s:3:"3,1";s:7:"sort_by";i:2;s:8:"sort_dir";s:3:"ASC";s:7:"show_as";s:6:"topics";}', $this->db->selectValue('SELECT search_data FROM pun_search_cache WHERE id=7'), 'stored as search.php stored it');

		$this->searches->pruneCache();
		$this->assertSame(2, $this->db->selectValue('SELECT COUNT(*) FROM pun_search_cache'));

		$this->searches->pruneCache('192.0.2.7', 'Anna');
		$this->assertSame(array(7), array_map(static fn ($row): int => $row->int('id'), $this->db->select('SELECT id FROM pun_search_cache')));
	}

	public function testWhatIsNotAStoredSearchReadsBackAsNone(): void {
		$this->db->execute('INSERT INTO pun_search_cache (id, ident, search_data) VALUES (9, ?, ?)', 'Anna', 'O:8:"stdClass":0:{}');

		$this->assertNull($this->searches->stored(9, 'Anna'));
		$this->assertNull(Searches::unserialized(9, 'Anna', 'not serialized'));
	}

	public function testAStoredSearchsPostsAndTopicsComeInItsSort(): void {
		$ids = static fn (array $results): array => array_map(static fn (ResultPostInterface|ResultTopicInterface $result): int => $result->id(), $results);

		$this->assertSame(array(2, 3, 1), $ids($this->results->posts(array(1, 2, 3), null, 'DESC')));
		$this->assertSame(array(1, 3), $ids($this->results->posts(array(3, 1), 1, 'ASC')));
		$this->assertSame(array(1, 3), $ids($this->results->topics(array(1, 3), 2, 'ASC', null)));
		$this->assertSame(array(3, 1), $ids($this->results->topics(array(1, 3), 3, 'DESC', null)));
		$this->assertSame(array(), $this->results->posts(array(), null, 'DESC'));

		$post = $this->results->posts(array(1), null, 'DESC')[0];
		$this->assertSame(array(1, 'Anna', 2, 100, 'first', true, 1, 'Anna', 'Cats', 1, 100, 300, 2, 'annabel', 1, 1, 'Open'),
			array($post->id(), $post->poster(), $post->posterId(), $post->posted(), $post->message(), $post->hidesSmilies(), $post->topicId(), $post->topicPoster(), $post->subject(),
				$post->firstPostId(), $post->topicPosted(), $post->lastPost(), $post->lastPostId(), $post->lastPoster(), $post->replyCount(), $post->forumId(), $post->forumName()));

		$topics = $this->results->topics(array(1, 2), null, 'ASC', 3);
		$this->assertSame(array(array(1, true, false, true), array(2, false, true, true)), array_map(static fn (ResultTopicInterface $topic): array => array($topic->id(), $topic->isSticky(), $topic->isClosed(), $topic->hasPosted()), $topics));
		$this->assertFalse($this->results->topics(array(1), null, 'ASC', 4)[0]->hasPosted());
	}

	public function testAPostWithoutAMessageReadsBackAsEmpty(): void {
		$this->db->execute('UPDATE pun_posts SET message=NULL WHERE id=1');

		$this->assertSame('', $this->results->posts(array(1), null, 'DESC')[0]->message());
	}

	public function testTheQuickSearchesFindWhatTheGroupMayRead(): void {
		$ids = static fn (array $results): array => array_map(static fn (ResultPostInterface|ResultTopicInterface $result): int => $result->id(), $results);

		$this->assertSame(array(3, 2, 1), $ids($this->results->newTopics(3, 100, null, null)));
		$this->assertSame(array(2, 1), $ids($this->results->newTopics(4, 100, null, 2)));
		$this->assertSame(array(2), $ids($this->results->newTopics(3, 400, 1, null)));
		$this->assertSame(array(3, 2), $ids($this->results->recentTopics(3, 400, null)));
		$this->assertSame(array(4, 5), $ids($this->results->userPosts(3, 4)));
		$this->assertSame(array(5), $ids($this->results->userPosts(4, 4)));
		$this->assertSame(array(2), $ids($this->results->userTopics(3, 3, null)));
		$this->assertTrue($this->results->userTopics(3, 2, 3)[0]->hasPosted());
		$this->assertSame(array(3, 1), $ids($this->results->subscribedTopics(3, 2, null)));
		$this->assertSame(array(1), $ids($this->results->subscribedTopics(4, 2, 2)));
		$this->assertSame(array(3, 2), $ids($this->results->unansweredTopics(3, null)));
		$this->assertTrue($this->results->newTopics(4, 100, null, 2)[1]->hasPosted());
	}

	public function testSubscribedForumsAndTheFormsForumsComeInTheBoardsOrder(): void {
		$forums = $this->results->subscribedForums(3, 2);

		$this->assertSame(array(2, 3, 1), array_map(static fn (ResultForumInterface $forum): int => $forum->id(), $forums));
		$this->assertSame(array(3, 1), array_map(static fn (ResultForumInterface $forum): int => $forum->id(), $this->results->subscribedForums(4, 2)));
		$this->assertSame(array(2, 'First', 3, 'Away', '', 'http://example.com/', 0, 0, null, null, null),
			array($forums[1]->categoryId(), $forums[1]->categoryName(), $forums[1]->id(), $forums[1]->name(), $forums[1]->description(), $forums[1]->redirectUrl(),
				$forums[1]->topicCount(), $forums[1]->postCount(), $forums[1]->lastPost(), $forums[1]->lastPostId(), $forums[1]->lastPoster()));
		$this->assertSame(array('About', 2, 3, 500, 3, 'bob'), array($forums[2]->description(), $forums[2]->topicCount(), $forums[2]->postCount(), $forums[2]->lastPost(), $forums[2]->lastPostId(), $forums[2]->lastPoster()));

		$searchable = $this->results->forums(3);
		$this->assertSame(array(array(2, 'First', 2, 'Hidden'), array(1, 'Second', 1, 'Open')),
			array_map(static fn ($forum): array => array($forum->categoryId(), $forum->categoryName(), $forum->id(), $forum->name()), $searchable));
		$this->assertCount(1, $this->results->forums(4));
	}
}
