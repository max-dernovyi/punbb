<?php
/**
 * forum_config_add() escapes what it is given.
 *
 * It is the helper an extension's install code calls to add a row to the
 * config table, and both of its arguments were interpolated into the INSERT
 * raw. The manifest that supplies them is admin-installed, so nothing reaches
 * it from a request — but it is a published API that builds SQL, and the rule
 * for those is that the value is neutralised where it enters the statement.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

/** Records the queries it is asked to run and escapes the way mysqli does. */
class ConfigQueryRecorder {
	public string $prefix = 'test_';

	/** @var list<array<string, mixed>> */
	public array $queries = array();

	/** @param array<string, mixed> $query */
	public function query_build(array $query, bool $return_sql = false): bool {
		$this->queries[] = $query;

		return true;
	}

	public function escape(?string $value): string {
		return str_replace(array('\\', '\''), array('\\\\', '\\\''), (string) $value);
	}
}

class ForumConfigAddTest extends TestCase
{
	private ConfigQueryRecorder $db;

	/** The bootstrap's globals, put back so the rest of the suite sees them. */
	private $savedDb;
	private $savedConfig;

	protected function setUp(): void
	{
		global $forum_db, $forum_config;

		require_once FORUM_ROOT.'include/common_admin.php';

		$this->savedDb = $forum_db;
		$this->savedConfig = $forum_config;

		$this->db = new ConfigQueryRecorder();
		$forum_db = $this->db;
		$forum_config = array();
	}

	protected function tearDown(): void
	{
		global $forum_db, $forum_config;

		$forum_db = $this->savedDb;
		$forum_config = $this->savedConfig;
	}

	public function testAQuoteInTheValueCannotCloseTheStringItIsWrittenInto(): void
	{
		forum_config_add('o_audit_value', 'a\', (SELECT 1)) -- ');

		$this->assertCount(1, $this->db->queries);
		$this->assertSame(
			'\'o_audit_value\', \'a\\\', (SELECT 1)) -- \'',
			$this->db->queries[0]['VALUES']
		);
	}

	public function testAQuoteInTheNameCannotCloseTheStringItIsWrittenInto(): void
	{
		forum_config_add('o_audit\', \'x\') -- ', '1');

		$this->assertCount(1, $this->db->queries);
		$this->assertSame(
			'\'o_audit\\\', \\\'x\\\') -- \', \'1\'',
			$this->db->queries[0]['VALUES']
		);
	}

	/** The plain case still writes the row it always wrote. */
	public function testAnOrdinaryValueIsWrittenUnchanged(): void
	{
		forum_config_add('o_audit_plain', 'plain');

		$this->assertSame('conf_name, conf_value', $this->db->queries[0]['INSERT']);
		$this->assertSame('config', $this->db->queries[0]['INTO']);
		$this->assertSame('\'o_audit_plain\', \'plain\'', $this->db->queries[0]['VALUES']);
	}

	/** An option the forum already carries is left alone, quotes or not. */
	public function testAnExistingOptionIsNotRewritten(): void
	{
		global $forum_config;

		$forum_config['o_audit_value'] = 'already here';

		forum_config_add('o_audit_value', 'a\'b');

		$this->assertSame(array(), $this->db->queries);
	}
}
