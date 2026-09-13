<?php
/**
 * Identifier quoting in the database layers.
 *
 * MySQL 8.0 turned GROUPS and RANK into reserved words. The forum schema has
 * a `groups` table and a `rank` column, and the mysqli drivers emitted both
 * unquoted, so a fresh install died in CREATE TABLE. Every generated
 * identifier is quoted now; the DDL half of this test also runs the
 * statements against a live server.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class IdentifierQuotingTest extends TestCase {
	/** @var array<string, string> */
	private static array $output = array();

	public static function mysqlDrivers(): array {
		return array('mysqli' => array('mysqli'), 'mysqli_innodb' => array('mysqli_innodb'));
	}

	public static function backtickDrivers(): array {
		return self::mysqlDrivers();
	}

	public static function standardQuoteDrivers(): array {
		return array('pgsql' => array('pgsql'), 'sqlite3' => array('sqlite3'));
	}

	private function run_harness(string $harness, string $driver): string {
		$key = $harness.':'.$driver;

		if (!isset(self::$output[$key]))
		{
			$command = escapeshellarg(PHP_BINARY).
				' -d display_errors=1 -d error_reporting=-1 '.
				escapeshellarg(__DIR__.'/'.$harness).' '.
				escapeshellarg($driver).' 2>&1';

			self::$output[$key] = (string)shell_exec($command);
		}

		return self::$output[$key];
	}

	private function ddl(string $driver): string {
		$output = $this->run_harness('mysqli_ddl_harness.php', $driver);

		if (strpos($output, 'NO_SERVER') !== false)
			$this->markTestSkipped('no MySQL server: set PUNBB_TEST_MYSQL_HOST');

		return $output;
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('backtickDrivers')]
	public function testTheMysqlDriversQuoteWithBackticks(string $driver): void {
		$output = $this->run_harness('quote_identifier_harness.php', $driver);

		$this->assertStringContainsString('PLAIN=`rank`', $output, $output);
		$this->assertStringContainsString('RESERVED_TABLE=`groups`', $output, $output);
		$this->assertStringContainsString('NORMAL=`username`', $output, $output);
		$this->assertStringContainsString('ESCAPED=`we``ird"one`', $output, $output);
	}

	/**
	 * 1.4 extensions hand the schema helpers names already in backticks, and
	 * 1.4.4 used them as given. Only a whole, well-formed quoted name passes.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('backtickDrivers')]
	public function testTheMysqlDriversKeepAWholeQuotedIdentifier(string $driver): void {
		$output = $this->run_harness('quote_identifier_harness.php', $driver);

		$this->assertStringContainsString('PREQUOTED=`rank`'."\n", $output, $output);
		$this->assertStringContainsString('PREQUOTED_ESCAPED=`we``ird`'."\n", $output, $output);
		$this->assertStringContainsString('PREQUOTED_OPEN=```a``b```'."\n", $output, $output);
		$this->assertStringContainsString('PREQUOTED_NEWLINE="```rank``\\n`"', $output, $output);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('standardQuoteDrivers')]
	public function testThePortableDriversQuoteWithDoubleQuotes(string $driver): void {
		$output = $this->run_harness('quote_identifier_harness.php', $driver);

		$this->assertStringContainsString('PLAIN="rank"', $output, $output);
		$this->assertStringContainsString('RESERVED_TABLE="groups"', $output, $output);
		$this->assertStringContainsString('ESCAPED="we`ird""one"', $output, $output);
		$this->assertStringContainsString('PREQUOTED="`rank`"', $output, $output);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('mysqlDrivers')]
	public function testCreateTableQuotesTheReservedTableAndColumn(string $driver): void {
		$ddl = $this->ddl($driver);

		$this->assertMatchesRegularExpression('/DDL=CREATE TABLE `ddl_\d+_groups` \(/', $ddl, $ddl);
		$this->assertStringContainsString('`rank` VARCHAR(50)', $ddl, $ddl);
		$this->assertStringContainsString('`id` INT(10) UNSIGNED AUTO_INCREMENT', $ddl, $ddl);
		$this->assertStringContainsString('PRIMARY KEY (`id`)', $ddl, $ddl);
		$this->assertMatchesRegularExpression('/UNIQUE KEY `ddl_\d+_groups_rank_idx`\(`rank`\)/', $ddl, $ddl);
		$this->assertMatchesRegularExpression('/KEY `ddl_\d+_groups_ident_idx`\(`ident`\(40\)\)/', $ddl, $ddl);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('mysqlDrivers')]
	public function testAPlainTableIsQuotedToo(string $driver): void {
		$ddl = $this->ddl($driver);

		$this->assertMatchesRegularExpression('/DDL=CREATE TABLE `ddl_\d+_normal` \( `id` INT\(10\) NOT NULL, PRIMARY KEY \(`id`\)/', $ddl, $ddl);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('mysqlDrivers')]
	public function testTheAlterBuildersQuoteTableAndColumnNames(string $driver): void {
		$ddl = $this->ddl($driver);

		$this->assertMatchesRegularExpression('/ALTER TABLE `ddl_\d+_groups` ADD `min_posts` INT\(10\) NOT NULL DEFAULT 0 AFTER `rank`/', $ddl, $ddl);
		$this->assertMatchesRegularExpression('/ALTER TABLE `ddl_\d+_groups` MODIFY `rank` VARCHAR\(60\)/', $ddl, $ddl);
		$this->assertMatchesRegularExpression('/ALTER TABLE `ddl_\d+_groups` ADD INDEX `ddl_\d+_groups_min_posts_idx` \(`min_posts`\)/', $ddl, $ddl);
		$this->assertMatchesRegularExpression('/ALTER TABLE `ddl_\d+_groups` DROP INDEX `ddl_\d+_groups_min_posts_idx`/', $ddl, $ddl);
		$this->assertMatchesRegularExpression('/ALTER TABLE `ddl_\d+_groups` DROP `min_posts`/', $ddl, $ddl);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('mysqlDrivers')]
	public function testTheSchemaIntrospectionSeesTheReservedNames(string $driver): void {
		$ddl = $this->ddl($driver);

		$this->assertStringContainsString('GROUPS_EXISTS=true', $ddl, $ddl);
		$this->assertStringContainsString('RANK_EXISTS=true', $ddl, $ddl);
		$this->assertStringContainsString('IDENT_INDEX_EXISTS=true', $ddl, $ddl);
		$this->assertStringContainsString('GROUPS_GONE=false', $ddl, $ddl);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('mysqlDrivers')]
	public function testQueryBuildQuotesTheTableReference(string $driver): void {
		$ddl = $this->ddl($driver);

		$this->assertMatchesRegularExpression('/SELECT_SQL=SELECT g\.id FROM `ddl_\d+_groups` AS g/', $ddl, $ddl);
		$this->assertMatchesRegularExpression('/INSERT_SQL=INSERT INTO `ddl_\d+_groups` \(`rank`, ident\)/', $ddl, $ddl);
		$this->assertMatchesRegularExpression('/JOIN_SQL=.*INNER JOIN `ddl_\d+_groups` AS g ON /', $ddl, $ddl);
		$this->assertMatchesRegularExpression('/UPDATE_SQL=UPDATE `ddl_\d+_groups` SET `rank`=/', $ddl, $ddl);
		$this->assertMatchesRegularExpression('/DELETE_SQL=DELETE FROM `ddl_\d+_groups` WHERE /', $ddl, $ddl);
	}

	/**
	 * A multi-table FROM is the shape an extension is most likely to write, and
	 * every table in it needs quoting for MySQL 8 just as a single one does.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('mysqlDrivers')]
	public function testQueryBuildQuotesEveryTableInAMultiTableFrom(string $driver): void {
		$ddl = $this->ddl($driver);

		$this->assertMatchesRegularExpression('/MULTI_FROM=SELECT 1 FROM `ddl_\d+_normal` AS n, `groups` AS g/', $ddl, $ddl);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('mysqlDrivers')]
	public function testQueryBuildLeavesAnUnrecognisedTableExpressionAlone(string $driver): void {
		$ddl = $this->ddl($driver);

		$this->assertMatchesRegularExpression('/RAW_FROM_UNTOUCHED=SELECT 1 FROM ddl_\d+_normal AS n USE INDEX \(ident\)/', $ddl, $ddl);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('mysqlDrivers')]
	public function testTheQuotedStatementsRunOnTheServer(string $driver): void {
		$ddl = $this->ddl($driver);

		$this->assertStringContainsString('ROW=New member', $ddl, $ddl);
		$this->assertStringContainsString('DONE', $ddl, $ddl);
	}

	/**
	 * The shared query fragments are raw SQL, so the reserved column is quoted
	 * at the call site through the driver rather than by query_build().
	 */
	public function testTheApplicationSqlNeverNamesTheReservedColumnBare(): void {
		$bare = array('\'rank, min_posts\'', '\'id, rank\'', '\'rank = \\\'', '\'rank=\\\'');

		foreach (array('admin/install.php', 'admin/ranks.php', 'admin/db_update.php') as $cur_file)
		{
			$source = (string)file_get_contents(FORUM_ROOT.$cur_file);

			$this->assertStringContainsString('quote_identifier(\'rank\')', $source, $cur_file);

			foreach ($bare as $cur_fragment)
				$this->assertStringNotContainsString($cur_fragment, $source, $cur_file.': '.$cur_fragment);
		}
	}

	private function prequoted(string $driver): string {
		$output = $this->run_harness('mysqli_prequoted_harness.php', $driver);

		if (strpos($output, 'NO_SERVER') !== false)
			$this->markTestSkipped('no MySQL server: set PUNBB_TEST_MYSQL_HOST');

		return $output;
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('mysqlDrivers')]
	public function testAPreQuotedSchemaBuildsThePlainColumns(string $driver): void {
		$output = $this->prequoted($driver);

		$this->assertStringContainsString('COLUMNS=id,indeteficate,position'."\n", $output, $output);
		$this->assertMatchesRegularExpression('/DDL=CREATE TABLE `pq_\d+_sidebar` \( `id` INT\(10\) UNSIGNED AUTO_INCREMENT NOT NULL, `indeteficate` VARCHAR\(50\) NOT NULL DEFAULT \'\', PRIMARY KEY \(`id`\), KEY `pq_\d+_sidebar_indeteficate_idx`\(`indeteficate`\(20\)\) \)/', $output, $output);
		$this->assertMatchesRegularExpression('/DDL=ALTER TABLE `pq_\d+_sidebar` ADD `position` INT\(10\) NOT NULL DEFAULT 0 AFTER `indeteficate`/', $output, $output);
		$this->assertStringNotContainsString('``', $output, $output);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('mysqlDrivers')]
	public function testAPreQuotedSchemaIsReachedByThePlainName(string $driver): void {
		$output = $this->prequoted($driver);

		$this->assertStringContainsString('ID_EXISTS=true', $output, $output);
		$this->assertStringContainsString('POSITION_EXISTS=true', $output, $output);
		$this->assertStringContainsString('INDEX_EXISTS=true', $output, $output);
		$this->assertStringContainsString('ROW=2,2,4', $output, $output);
		$this->assertStringContainsString('POSITION_GONE=false', $output, $output);
		$this->assertStringContainsString('SIDEBAR_GONE=false', $output, $output);
		$this->assertStringNotContainsString('ERROR:', $output, $output);
		$this->assertStringContainsString('DONE', $output, $output);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('mysqlDrivers')]
	public function testNoDiagnosticReachesTheOutput(string $driver): void {
		$output = $this->ddl($driver).$this->prequoted($driver);

		foreach (array('Fatal error', 'Uncaught', 'Parse error', 'Warning:', 'Deprecated:', 'Notice:', 'ERROR:') as $marker)
			$this->assertStringNotContainsString($marker, $output, $output);
	}
}
