<?php
/**
 * Prepared statements on every supported database: parameters bound by type,
 * rows read back typed, the table prefix, and a refusal reported at the call
 * site that ran it. SQLite3 always runs; MySQL and PostgreSQL run against the
 * servers PUNBB_TEST_MYSQL_* and PUNBB_TEST_PGSQL_* name, and skip without one.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\DatabaseException;
use PunBB\Module\Database\Sql\Driver\DriverInterface;
use PunBB\Module\Database\Sql\Driver\MysqliDriver;
use PunBB\Module\Database\Sql\Driver\PgsqlDriver;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Database\Sql\QueryException;

class ConnectionTest extends TestCase {
	/** @var list<array{string, float}> */
	private array $logged = array();

	/** @var list<QueryException> */
	private array $failures = array();

	private ?Closure $close = null;

	protected function tearDown(): void {
		if ($this->close !== null)
			($this->close)();
	}

	/** @return array<string, array{string}> */
	public static function platformProvider(): array {
		return array('sqlite3' => array('sqlite3'), 'mysqli' => array('mysqli'), 'pgsql' => array('pgsql'));
	}

	private function driver(string $platform): DriverInterface {
		switch ($platform)
		{
			case 'sqlite3':
				$link = new SQLite3(':memory:');
				$this->close = static fn () => $link->close();

				return new Sqlite3Driver($link);

			case 'mysqli':
				$host = (string) getenv('PUNBB_TEST_MYSQL_HOST');
				if ($host === '')
					$this->markTestSkipped('no MySQL server: set PUNBB_TEST_MYSQL_HOST');

				mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
				$link = new mysqli($host, (string) getenv('PUNBB_TEST_MYSQL_USER'), (string) getenv('PUNBB_TEST_MYSQL_PASSWORD'), (string) getenv('PUNBB_TEST_MYSQL_DBNAME'));
				$link->set_charset('utf8mb4');
				$this->close = static function () use ($link): void {
					$link->query('DROP TABLE IF EXISTS '.self::prefix().'items');
					$link->close();
				};

				return new MysqliDriver($link);

			default:
				$host = (string) getenv('PUNBB_TEST_PGSQL_HOST');
				if ($host === '')
					$this->markTestSkipped('no PostgreSQL server: set PUNBB_TEST_PGSQL_HOST');

				$link = pg_connect('host='.$host.' dbname='.getenv('PUNBB_TEST_PGSQL_DBNAME').' user='.getenv('PUNBB_TEST_PGSQL_USER').' password='.getenv('PUNBB_TEST_PGSQL_PASSWORD'), PGSQL_CONNECT_FORCE_NEW);
				$this->close = static function () use ($link): void {
					pg_query($link, 'DROP TABLE IF EXISTS '.self::prefix().'items');
					pg_close($link);
				};

				return new PgsqlDriver($link);
		}
	}

	private static function prefix(): string {
		return 'conn_'.getmypid().'_';
	}

	private function connection(string $platform): Connection {
		$connection = new Connection($this->driver($platform), self::prefix(),
			function (string $sql, float $seconds): void { $this->logged[] = array($sql, $seconds); },
			function (QueryException $e): void { $this->failures[] = $e; });

		$connection->execute('DROP TABLE IF EXISTS '.$connection->table('items'));
		$connection->execute('CREATE TABLE '.$connection->table('items').' (id INTEGER NOT NULL, label VARCHAR(40) NOT NULL, note VARCHAR(40), score FLOAT)');

		return $connection;
	}

	#[DataProvider('platformProvider')]
	public function testParametersAreBoundByTypeAndRowsReadBackTyped(string $platform): void {
		$db = $this->connection($platform);
		$table = $db->table('items');

		foreach (array(array(1, 'O\'Reilly ?', null, 1.5), array(2, 'Zoë', 'second', null), array(3, 'plain', 'third', 2.25)) as $values)
			$this->assertSame(1, $db->execute('INSERT INTO '.$table.' (id, label, note, score) VALUES (?, ?, ?, ?)', ...$values));

		$rows = $db->select('SELECT id, label, note FROM '.$table.' WHERE id > ? ORDER BY id LIMIT ? OFFSET ?', 0, 2, 1);

		$this->assertCount(2, $rows);
		$this->assertSame(2, $rows[0]->int('id'));
		$this->assertSame('Zoë', $rows[0]->string('label'));
		$this->assertSame('second', $rows[0]->nullableString('note'));
		$this->assertSame(3, $rows[1]->int('id'));

		$quoted = $db->selectRow('SELECT id, note FROM '.$table.' WHERE label = ?', 'O\'Reilly ?');
		$this->assertNotNull($quoted);
		$this->assertSame(1, $quoted->int('id'));
		$this->assertNull($quoted->nullableString('note'));
		$this->assertNull($quoted->nullableInt('note'));

		$this->assertSame(1, (int) $db->selectValue('SELECT COUNT(*) FROM '.$table.' WHERE note IS NULL'));
		$this->assertNull($db->selectRow('SELECT id FROM '.$table.' WHERE id = ?', 99));
		$this->assertNull($db->selectValue('SELECT id FROM '.$table.' WHERE id = ?', 99));
		$this->assertSame(2, $db->execute('UPDATE '.$table.' SET note = ? WHERE id < ?', 'changed', 3));
		$this->assertSame(3, (int) $db->selectValue('SELECT COUNT(*) FROM '.$table.' WHERE label '.$db->platform()->likeIgnoringCase().' ?', '%'));
	}

	#[DataProvider('platformProvider')]
	public function testATrueOrFalseParameterIsBoundAsOneOrZero(string $platform): void {
		$db = $this->connection($platform);
		$db->execute('INSERT INTO '.$db->table('items').' (id, label) VALUES (?, ?)', true, 'flag');

		$this->assertSame(1, $db->selectRow('SELECT id FROM '.$db->table('items').' WHERE id = ?', true)?->int('id'));
	}

	#[DataProvider('platformProvider')]
	public function testEveryStatementIsLogged(string $platform): void {
		$db = $this->connection($platform);
		$this->logged = array();

		$db->select('SELECT id FROM '.$db->table('items').' WHERE id = ?', 1);

		$this->assertCount(1, $this->logged);
		$this->assertSame('SELECT id FROM '.$db->table('items').' WHERE id = ?', $this->logged[0][0]);
		$this->assertGreaterThanOrEqual(0.0, $this->logged[0][1]);
	}

	#[DataProvider('platformProvider')]
	public function testARefusedStatementIsReportedAtItsCallSiteAndThrown(string $platform): void {
		$db = $this->connection($platform);

		try {
			$db->select('SELECT nothing FROM '.$db->table('absent').' WHERE id = ?', 1);
			$this->fail('the statement was not refused');
		}
		catch (QueryException $e) {
			$this->assertSame(__FILE__, $e->callFile());
			$this->assertSame(__LINE__ - 5, $e->callLine());
			$this->assertSame('SELECT nothing FROM '.$db->table('absent').' WHERE id = ?', $e->sql());
			$this->assertNotSame('', $e->getMessage());
			$this->assertSame(array($e), $this->failures);
		}
	}

	/** An unprefixed forum names its tables groups and ranks, which MySQL 8 reserves. */
	#[DataProvider('platformProvider')]
	public function testATableNamedAfterAReservedWordCanBeRead(string $platform): void {
		$db = new Connection($this->driver($platform), '');
		$window = $db->table('window');

		$close = $this->close;
		$this->close = static function () use ($db, $window, $close): void {
			$db->execute('DROP TABLE IF EXISTS '.$window);
			if ($close !== null)
				$close();
		};

		$db->execute('DROP TABLE IF EXISTS '.$window);
		$db->execute('CREATE TABLE '.$window.' (id INTEGER NOT NULL)');
		$db->execute('INSERT INTO '.$window.' (id) VALUES (?)', 7);

		$this->assertSame(7, $db->selectRow('SELECT w.id FROM '.$window.' AS w')?->int('id'));
	}

	/** The id an INSERT gave its row is read back from the connection. */
	#[DataProvider('platformProvider')]
	public function testTheLastInsertedIdIsTheOneTheDatabaseGave(string $platform): void {
		$db = $this->connection($platform);
		$serials = $db->table('serials');
		$db->execute(match ($platform) {
			'sqlite3'	=> 'CREATE TEMP TABLE '.$serials.' (id INTEGER PRIMARY KEY, label VARCHAR(40) NOT NULL)',
			'mysqli'	=> 'CREATE TEMPORARY TABLE '.$serials.' (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, label VARCHAR(40) NOT NULL)',
			default		=> 'CREATE TEMPORARY TABLE '.$serials.' (id SERIAL PRIMARY KEY, label VARCHAR(40) NOT NULL)',
		});

		$db->execute('INSERT INTO '.$serials.' (label) VALUES (?)', 'first');
		$this->assertSame(1, $db->lastInsertId());

		$db->execute('INSERT INTO '.$serials.' (label) VALUES (?)', 'second');
		$this->assertSame(2, $db->lastInsertId());
	}

	public function testPostgresqlPlaceholdersAreNumberedOutsideQuotes(): void {
		$this->assertSame('SELECT \'?\', "a?b" FROM t WHERE a = $1 AND b = \'it\'\'s ?\' AND c IN ($2, $3)',
			PgsqlDriver::numbered('SELECT \'?\', "a?b" FROM t WHERE a = ? AND b = \'it\'\'s ?\' AND c IN (?, ?)'));
	}

	public function testATableNameIsPrefixedAndNothingElseIsATableName(): void {
		$db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');

		$this->assertSame('"pun_users"', $db->table('users'));
		$this->assertSame('`pun_groups`', Platform::Mysql->quoteIdentifier('pun_groups'));
		$this->assertSame('"a""b"', Platform::Pgsql->quoteIdentifier('a"b'));
		$this->assertSame('`a``b`', Platform::Mysql->quoteIdentifier('a`b'));
		$this->expectException(DatabaseException::class);
		$db->table('users; DROP TABLE users');
	}

	public function testAPostgresqlTableIsNamedAsTheLegacyLayerCreatedIt(): void {
		$driver = $this->createStub(DriverInterface::class);
		$driver->method('platform')->willReturn(Platform::Pgsql);

		$this->assertSame('"punbb_users"', (new Connection($driver, 'PunBB_'))->table('users'));
	}

	public function testAColumnTheRowDoesNotHaveIsAnError(): void {
		$db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), '');
		$row = $db->selectRow('SELECT 1 AS one, NULL AS absent_note');

		$this->assertNotNull($row);
		$this->assertSame(1, $row->int('one'));

		try {
			$row->int('absent_note');
			$this->fail('NULL read as an integer');
		}
		catch (DatabaseException $e) {
			$this->assertStringContainsString('absent_note', $e->getMessage());
		}

		$this->expectException(DatabaseException::class);
		$row->string('two');
	}

	public function testThePlatformsNameTheirCaseInsensitiveLike(): void {
		$this->assertSame('ILIKE', Platform::Pgsql->likeIgnoringCase());
		$this->assertSame('LIKE', Platform::Mysql->likeIgnoringCase());
		$this->assertSame('LIKE', Platform::Sqlite->likeIgnoringCase());
	}
}
