<?php
/**
 * The search index writers, driven against a recording database stub.
 *
 * Both cases below were found by the functional pass (`make test-flows`), which
 * posts and moderates over HTTP where the entry-point sweep only reads pages:
 *
 *  - every reply posted emitted a deprecation, because a reply has no subject
 *    of its own and the null reached mbstring;
 *  - deleting a forum whose only topic had no posts (what a move leaves behind)
 *    built `post_id IN()` and ended on the forum's error page.
 *
 * The suite runs with failOnDeprecation, so the first one fails these tests by
 * itself; the second is asserted on the queries the stub was asked to run.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

/** A result set the recorder hands back: the rows programmed for that query. */
class SearchIndexResult {
	/** @param list<array<string, string>> $rows */
	public function __construct(public array $rows) {}
}

/**
 * Records what it is asked to run and answers with the rows the test programmed.
 *
 * Without programmed rows every SELECT comes back empty, which leaves the edit
 * and strip branches — the ones that build `IN(...)` lists out of what the
 * database returned — unreachable. $rows maps a needle in the SQL to the rows
 * that query answers with, consumed once.
 */
class SearchIndexRecorder {
	public string $prefix = 'test_';

	/** @var list<string> */
	public array $queries = array();

	/** @var array<string, list<array<string, string>>> */
	public array $rows = array();

	/** @param array<string, mixed> $query */
	public function query_build(array $query, bool $return_sql = false): string|SearchIndexResult {
		$sql = '';

		foreach (array('SELECT', 'INSERT', 'INTO', 'DELETE', 'FROM', 'WHERE', 'GROUP BY', 'HAVING') as $part)
			if (isset($query[$part]))
				$sql .= $part.' '.(is_array($query[$part]) ? implode(',', $query[$part]) : $query[$part]).' ';

		$sql = trim($sql);

		return $return_sql ? $sql : $this->record($sql);
	}

	public function query(string $sql): SearchIndexResult {
		return $this->record($sql);
	}

	/** @return array<string, string>|false */
	public function fetch_assoc($result): array|false {
		if (!$result instanceof SearchIndexResult || $result->rows === array())
			return false;

		return array_shift($result->rows);
	}

	public function free_result($result): bool {
		return true;
	}

	public function escape(?string $value): string {
		return str_replace('\'', '\\\'', (string) $value);
	}

	private function record(string $sql): SearchIndexResult {
		$this->queries[] = $sql;

		foreach ($this->rows as $needle => $rows) {
			if (strpos($sql, $needle) !== false) {
				unset($this->rows[$needle]);

				return new SearchIndexResult($rows);
			}
		}

		return new SearchIndexResult(array());
	}
}

class SearchIndexTest extends TestCase {
	private SearchIndexRecorder $db;

	public static function setUpBeforeClass(): void {
		require_once FORUM_ROOT.'include/search_idx.php';
	}

	/** @var array<string, mixed> */
	private array $globals = array();

	protected function setUp(): void {
		global $forum_db, $db_type;

		$this->globals = array(
			'forum_db' => $GLOBALS['forum_db'] ?? null,
			'db_type' => $GLOBALS['db_type'] ?? null,
		);

		$this->db = new SearchIndexRecorder();
		$forum_db = $this->db;
		$db_type = 'sqlite3';
	}

	protected function tearDown(): void {
		foreach ($this->globals as $name => $value) {
			if ($value === null)
				unset($GLOBALS[$name]);
			else
				$GLOBALS[$name] = $value;
		}
	}

	/** A reply: update_search_index() is called with no subject at all. */
	public function testIndexingAReplyPassesNoNullToMbstring(): void {
		update_search_index('post', 17, 'A reply about zwitterions and things');

		$this->assertNotSame(array(), $this->db->queries);
		$this->assertSame(array(), $this->subjectMatchInserts(17));
	}

	public function testIndexingAReplyWithAnExplicitNullSubject(): void {
		update_search_index('post', 17, 'Another reply worth indexing', null);

		$this->assertNotSame(array(), $this->db->queries);
		$this->assertSame(array(), $this->subjectMatchInserts(17));
	}

	/** A topic: the subject is indexed alongside the message. */
	public function testIndexingATopicIndexesItsSubjectToo(): void {
		update_search_index('post', 18, 'The message body', 'Ümlaut subject');

		$subject_matches = array_filter(
			$this->db->queries,
			static fn(string $sql): bool => strpos($sql, 'search_matches (post_id, word_id, subject_match)') !== false
				&& strpos($sql, '18, id, 1') !== false
		);

		$this->assertNotSame(array(), $subject_matches, implode(' || ', $this->db->queries));
	}

	/** An edit reaches the branch that reads the current words back first. */
	public function testEditingReadsTheCurrentWordsBack(): void {
		update_search_index('edit', 19, 'The edited message', 'Edited subject');

		$this->assertStringContainsString('m.post_id=19', $this->db->queries[0]);
	}

	/**
	 * An edit that drops a word from the message and one from the subject: the
	 * words the database returned have to come back as two separate DELETEs.
	 */
	public function testEditingDeletesTheMatchesTheWordsNoLongerHave(): void {
		$this->db->rows = array(
			'm.post_id=19' => array(
				array('id' => '11', 'word' => 'dropped', 'subject_match' => '0'),
				array('id' => '12', 'word' => 'kept', 'subject_match' => '0'),
				array('id' => '13', 'word' => 'gone', 'subject_match' => '1'),
			),
		);

		update_search_index('edit', 19, 'kept', 'edited');

		$this->assertContains(
			'DELETE search_matches WHERE word_id IN (11) AND post_id=19 AND subject_match=0',
			$this->db->queries
		);
		$this->assertContains(
			'DELETE search_matches WHERE word_id IN (13) AND post_id=19 AND subject_match=1',
			$this->db->queries
		);
	}

	/** Words the database already knows must not be inserted into search_words again. */
	public function testWordsTheIndexAlreadyHasAreNotInsertedAgain(): void {
		$this->db->rows = array(
			'SELECT id, word FROM search_words' => array(
				array('id' => '21', 'word' => 'edited'),
				array('id' => '22', 'word' => 'body'),
			),
		);

		update_search_index('post', 20, 'edited body');

		$inserts = array_values(array_filter(
			$this->db->queries,
			static fn(string $sql): bool => strpos($sql, 'INSERT word INTO search_words') === 0
		));

		$this->assertSame(array(), $inserts, implode(' || ', $this->db->queries));
	}

	/** ...and a word it does not have must be. */
	public function testWordsTheIndexLacksAreInserted(): void {
		update_search_index('post', 21, 'entirely unheard vocabulary');

		$inserts = array_values(array_filter(
			$this->db->queries,
			static fn(string $sql): bool => strpos($sql, 'INSERT word INTO search_words') === 0
		));

		$this->assertNotSame(array(), $inserts, implode(' || ', $this->db->queries));
	}

	public function testStrippingAnEmptyPostListRunsNoQuery(): void {
		strip_search_index(array());

		$this->assertSame(array(), $this->db->queries);
	}

	public function testStrippingAnEmptyStringRunsNoQuery(): void {
		strip_search_index('');

		$this->assertSame(array(), $this->db->queries);
	}

	public function testStrippingRealPostIdsQueriesThem(): void {
		strip_search_index(array(3, 4));

		$this->assertStringContainsString('post_id IN(3,4)', $this->db->queries[0]);
	}

	public function testStrippingASinglePostIdQueriesIt(): void {
		strip_search_index(7);

		$this->assertStringContainsString('post_id IN(7)', $this->db->queries[0]);
	}

	/**
	 * A strip whose posts carry words: the second query asks which of them are
	 * now orphaned, and only those are deleted from search_words.
	 */
	public function testStrippingDeletesTheWordsLeftWithoutAMatch(): void {
		$this->db->rows = array(
			'GROUP BY word_id' => array(
				array('word_id' => '31'),
				array('word_id' => '32'),
			),
			'HAVING COUNT(word_id)=1' => array(
				array('word_id' => '32'),
			),
		);

		strip_search_index(array(3, 4));

		$this->assertContains(
			'SELECT word_id FROM search_matches WHERE word_id IN(31,32) GROUP BY word_id, subject_match HAVING COUNT(word_id)=1',
			$this->db->queries
		);
		$this->assertContains('DELETE search_words WHERE id IN(32)', $this->db->queries);
		$this->assertContains('DELETE search_matches WHERE post_id IN(3,4)', $this->db->queries);
	}

	/** Every `INSERT ... subject_match` the recorder saw for $post_id, subject rows only. */
	private function subjectMatchInserts(int $post_id): array {
		return array_values(array_filter(
			$this->db->queries,
			static fn(string $sql): bool => strpos($sql, 'search_matches (post_id, word_id, subject_match)') !== false
				&& strpos($sql, $post_id.', id, 1') !== false
		));
	}
}
