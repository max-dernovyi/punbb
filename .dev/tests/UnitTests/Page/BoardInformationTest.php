<?php
/**
 * The administration index's repository over an in-memory SQLite database:
 * the visitors online, the installed hotfixes, and the database server — also
 * MySQL's and PostgreSQL's, on the servers PUNBB_TEST_MYSQL_* and
 * PUNBB_TEST_PGSQL_* name, skipped without one.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\AdminIndex\Model\BoardInformation;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\MysqliDriver;
use PunBB\Module\Database\Sql\Driver\PgsqlDriver;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;

class BoardInformationTest extends TestCase {
	private BoardInformation $information;

	protected function setUp(): void {
		$db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');

		$db->execute('CREATE TABLE pun_online (user_id INTEGER NOT NULL, ident VARCHAR(200) NOT NULL, idle INTEGER NOT NULL)');
		$db->execute('CREATE TABLE pun_extensions (id VARCHAR(150) NOT NULL PRIMARY KEY)');
		$db->execute('INSERT INTO pun_online (user_id, ident, idle) VALUES (1, ?, 0), (2, ?, 0), (3, ?, 1)', '192.0.2.1', 'admin', 'idle');
		$db->execute('INSERT INTO pun_extensions (id) VALUES (?), (?), (?)', 'hotfix_1', 'pun_repository', 'hotfix_2');

		$this->information = new BoardInformation($db);
	}

	public function testTheVisitorsOnlineAreThoseNotIdle(): void {
		$this->assertSame(2, $this->information->onlineCount());
	}

	public function testTheHotfixesAreTheExtensionsNamedSo(): void {
		$hotfixes = $this->information->hotfixes();
		sort($hotfixes);

		$this->assertSame(array('hotfix_1', 'hotfix_2'), $hotfixes);
	}

	public function testSqliteReportsItsVersionAndNoFigures(): void {
		$database = $this->information->database();

		$this->assertSame(array('SQLite3', SQLite3::version()['versionString'], null, null), array($database->name(), $database->version(), $database->rows(), $database->size()));
	}

	public function testMySqlReportsItsVersionAndWhatTheForumsTablesHold(): void {
		$host = (string) getenv('PUNBB_TEST_MYSQL_HOST');
		if ($host === '')
			$this->markTestSkipped('no MySQL server: set PUNBB_TEST_MYSQL_HOST');

		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		$link = new mysqli($host, (string) getenv('PUNBB_TEST_MYSQL_USER'), (string) getenv('PUNBB_TEST_MYSQL_PASSWORD'), (string) getenv('PUNBB_TEST_MYSQL_DBNAME'));
		$prefix = 'ain_'.getmypid().'_';

		try {
			$link->query('CREATE TABLE '.$prefix.'items (id INT NOT NULL PRIMARY KEY, name VARCHAR(20) NOT NULL) ENGINE=InnoDB');
			$link->query('INSERT INTO '.$prefix.'items (id, name) VALUES (1, \'a\'), (2, \'b\')');
			$link->query('ANALYZE TABLE '.$prefix.'items');

			$database = (new BoardInformation(new Connection(new MysqliDriver($link), $prefix)))->database();

			$this->assertSame('MySQL', $database->name());
			$this->assertMatchesRegularExpression('/^[0-9]+\.[0-9]+\.[0-9]+$/', $database->version());
			$this->assertIsInt($database->rows());
			$this->assertGreaterThan(0, $database->size());
		}
		finally {
			$link->query('DROP TABLE IF EXISTS '.$prefix.'items');
			$link->close();
		}
	}

	public function testPostgresqlReportsItsVersionAndNoFigures(): void {
		$host = (string) getenv('PUNBB_TEST_PGSQL_HOST');
		if ($host === '')
			$this->markTestSkipped('no PostgreSQL server: set PUNBB_TEST_PGSQL_HOST');

		$link = pg_connect('host='.$host.' dbname='.getenv('PUNBB_TEST_PGSQL_DBNAME').' user='.getenv('PUNBB_TEST_PGSQL_USER').' password='.getenv('PUNBB_TEST_PGSQL_PASSWORD'), PGSQL_CONNECT_FORCE_NEW);

		try {
			$database = (new BoardInformation(new Connection(new PgsqlDriver($link), 'pun_')))->database();

			$this->assertSame(array('PostgreSQL', null, null), array($database->name(), $database->rows(), $database->size()));
			$this->assertMatchesRegularExpression('/^[0-9]+\.[0-9]+/', $database->version());
		}
		finally {
			pg_close($link);
		}
	}
}
