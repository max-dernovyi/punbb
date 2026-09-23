<?php
/**
 * The differ, constructed directly over a declared table and one as each
 * database reports it: what it asks for, and what it leaves alone.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Schema\Change\AddColumn;
use PunBB\Module\Database\Schema\Change\AddIndex;
use PunBB\Module\Database\Schema\Change\AlterColumn;
use PunBB\Module\Database\Schema\Change\CreateTable;
use PunBB\Module\Database\Schema\Change\DropColumn;
use PunBB\Module\Database\Schema\Change\DropIndex;
use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\InstalledColumn;
use PunBB\Module\Database\Schema\InstalledIndex;
use PunBB\Module\Database\Schema\InstalledTable;
use PunBB\Module\Database\Schema\SchemaDiffer;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Sql\Platform;

class SchemaDifferTest extends TestCase {
	private static function users(): Table {
		return new Table('users', array(
			new Column('id', 'SERIAL'),
			new Column('username', 'VARCHAR(200)', false, ''),
			new Column('password', 'VARCHAR(255)', false, ''),
			new Column('show_sig', 'TINYINT(1)', false, 1),
			new Column('timezone', 'FLOAT', false, 0),
			new Column('last_post', 'INT(10) UNSIGNED', true),
			new Column('signature', 'TEXT', true),
		), array('id'), array(), array('username_idx' => array('username(8)')));
	}

	/**
	 * users() as MySQL 8 reports it after create_table().
	 *
	 * @param array<string, ?InstalledColumn> $replaced a column by name, null to leave it out
	 * @param ?list<InstalledIndex> $indexes
	 */
	private static function mysqlUsers(array $replaced = array(), ?array $indexes = null): InstalledTable {
		$columns = array(
			'id'		=> new InstalledColumn('id', 'int unsigned', false, null),
			'username'	=> new InstalledColumn('username', 'varchar(200)', false, '', 'utf8mb3_general_ci'),
			'password'	=> new InstalledColumn('password', 'varchar(255)', false, '', 'utf8mb3_general_ci'),
			'show_sig'	=> new InstalledColumn('show_sig', 'tinyint(1)', false, '1'),
			'timezone'	=> new InstalledColumn('timezone', 'float', false, '0'),
			'last_post'	=> new InstalledColumn('last_post', 'int unsigned', true, null),
			'signature'	=> new InstalledColumn('signature', 'text', true, null, 'utf8mb3_general_ci'),
		);

		return new InstalledTable('users', array_values(array_filter(array_replace($columns, $replaced))), array('id'), $indexes ?? array(new InstalledIndex('username_idx', array('username(8)'), false)));
	}

	public function testATableTheDatabaseLacksIsCreatedWhole(): void {
		$this->assertEquals(array(new CreateTable(self::users())), (new SchemaDiffer())->diff(self::users(), null, Platform::Mysql));
	}

	public function testATableAsMysqlReportsItIsWhatWasDeclared(): void {
		$this->assertSame(array(), (new SchemaDiffer())->diff(self::users(), self::mysqlUsers(), Platform::Mysql));
	}

	public function testATableAsPostgresqlReportsItIsWhatWasDeclared(): void {
		$declared = new Table('users', self::users()->columns, array('id'), array(), array('username_idx' => array('username')));
		$installed = new InstalledTable('users', array(
			new InstalledColumn('id', 'integer', false, null),
			new InstalledColumn('username', 'character varying(200)', false, ''),
			new InstalledColumn('password', 'character varying(255)', false, ''),
			new InstalledColumn('show_sig', 'smallint', false, '1'),
			new InstalledColumn('timezone', 'real', false, '0'),
			new InstalledColumn('last_post', 'integer', true, null),
			new InstalledColumn('signature', 'text', true, null),
		), array('id'), array(new InstalledIndex('username_idx', array('username'), false)));

		$this->assertSame(array(), (new SchemaDiffer())->diff($declared, $installed, Platform::Pgsql));
	}

	public function testATableAsSqliteReportsItIsWhatWasDeclared(): void {
		$declared = new Table('users', self::users()->columns, array('id'), array(), array('username_idx' => array('username')));
		$installed = new InstalledTable('users', array(
			new InstalledColumn('id', 'integer', false, null),
			new InstalledColumn('username', 'varchar(200)', false, ''),
			new InstalledColumn('password', 'varchar(255)', false, ''),
			new InstalledColumn('show_sig', 'integer', false, '1'),
			new InstalledColumn('timezone', 'float', false, '0'),
			new InstalledColumn('last_post', 'integer', true, null),
			new InstalledColumn('signature', 'text', true, null),
		), array('id'), array(new InstalledIndex('username_idx', array('username'), false)));

		$this->assertSame(array(), (new SchemaDiffer())->diff($declared, $installed, Platform::Sqlite));
	}

	public function testAMissingColumnIsAddedAfterTheColumnDeclaredBeforeIt(): void {
		$changes = (new SchemaDiffer())->diff(self::users(), self::mysqlUsers(array('show_sig' => null, 'last_post' => null)), Platform::Mysql);

		$this->assertEquals(array(
			new AddColumn('users', self::users()->columns[3], 'password'),
			new AddColumn('users', self::users()->columns[5], 'timezone'),
		), $changes);
	}

	public function testTheVarchar40PasswordColumnIsWidened(): void {
		$installed = self::mysqlUsers(array('password' => new InstalledColumn('password', 'varchar(40)', false, '', 'utf8mb3_general_ci')));

		$this->assertEquals(array(new AlterColumn('users', self::users()->columns[2])), (new SchemaDiffer())->diff(self::users(), $installed, Platform::Mysql));
	}

	public function testAColumnOfAnotherSignNullabilityOrDefaultIsAltered(): void {
		$installed = self::mysqlUsers(array(
			'last_post'	=> new InstalledColumn('last_post', 'int', true, null),
			'username'	=> new InstalledColumn('username', 'varchar(200)', true, ''),
			'show_sig'	=> new InstalledColumn('show_sig', 'tinyint(1)', false, '0'),
		));

		$altered = array_map(static fn (object $change): string => $change instanceof AlterColumn ? $change->column->name : $change::class, (new SchemaDiffer())->diff(self::users(), $installed, Platform::Mysql));

		$this->assertSame(array('username', 'show_sig', 'last_post'), $altered);
	}

	public function testMysqlIgnoresTheDisplayWidthOfAnInteger(): void {
		$installed = self::mysqlUsers(array(
			'show_sig'	=> new InstalledColumn('show_sig', 'tinyint(3)', false, '1'),
			'last_post'	=> new InstalledColumn('last_post', 'int(10) unsigned', true, null),
		));

		$this->assertSame(array(), (new SchemaDiffer())->diff(self::users(), $installed, Platform::Mysql));
	}

	public function testSqliteComparesAffinityNotLength(): void {
		$declared = new Table('t', array(new Column('password', 'VARCHAR(255)', false, ''), new Column('n', 'INT(10) UNSIGNED', false, 0)));

		$this->assertSame(array(), (new SchemaDiffer())->diff($declared, new InstalledTable('t', array(new InstalledColumn('password', 'varchar(40)', false, ''), new InstalledColumn('n', 'integer', false, '0'))), Platform::Sqlite));
		$this->assertCount(1, (new SchemaDiffer())->diff($declared, new InstalledTable('t', array(new InstalledColumn('password', 'integer', false, ''), new InstalledColumn('n', 'integer', false, '0'))), Platform::Sqlite));
	}

	public function testPostgresqlComparesTheTypeItTranslatesTo(): void {
		$declared = new Table('t', array(new Column('password', 'VARCHAR(255)', false, ''), new Column('n', 'MEDIUMINT(8) UNSIGNED', false, 0)));

		$this->assertCount(1, (new SchemaDiffer())->diff($declared, new InstalledTable('t', array(new InstalledColumn('password', 'character varying(40)', false, ''), new InstalledColumn('n', 'integer', false, '0'))), Platform::Pgsql));
		$this->assertCount(1, (new SchemaDiffer())->diff($declared, new InstalledTable('t', array(new InstalledColumn('password', 'character varying(255)', false, ''), new InstalledColumn('n', 'smallint', false, '0'))), Platform::Pgsql));
	}

	public function testACollationIsComparedWhereTheDatabaseReportsOne(): void {
		$declared = new Table('search_words', array(new Column('word', 'VARCHAR(20)', false, '', 'bin')));

		$this->assertSame(array(), (new SchemaDiffer())->diff($declared, new InstalledTable('search_words', array(new InstalledColumn('word', 'varchar(20)', false, '', 'utf8mb3_bin'))), Platform::Mysql));
		$this->assertCount(1, (new SchemaDiffer())->diff($declared, new InstalledTable('search_words', array(new InstalledColumn('word', 'varchar(20)', false, '', 'latin1_swedish_ci'))), Platform::Mysql));
		$this->assertSame(array(), (new SchemaDiffer())->diff($declared, new InstalledTable('search_words', array(new InstalledColumn('word', 'character varying(20)', false, ''))), Platform::Pgsql));
	}

	public function testAMissingIndexIsAdded(): void {
		$this->assertEquals(array(new AddIndex('users', 'username_idx', array('username(8)'), false)), (new SchemaDiffer())->diff(self::users(), self::mysqlUsers(indexes: array()), Platform::Mysql));
	}

	public function testAnIndexOverOtherColumnsIsDroppedAndAddedAgain(): void {
		$installed = self::mysqlUsers(indexes: array(new InstalledIndex('username_idx', array('username(25)'), false)));

		$this->assertEquals(array(
			new DropIndex('users', 'username_idx'),
			new AddIndex('users', 'username_idx', array('username(8)'), false),
		), (new SchemaDiffer())->diff(self::users(), $installed, Platform::Mysql));
	}

	public function testAnIndexThatIsUniqueWhereThePlainOneIsDeclaredIsDroppedAndAddedAgain(): void {
		$installed = self::mysqlUsers(indexes: array(new InstalledIndex('username_idx', array('username(8)'), true)));

		$this->assertEquals(array(
			new DropIndex('users', 'username_idx'),
			new AddIndex('users', 'username_idx', array('username(8)'), false),
		), (new SchemaDiffer())->diff(self::users(), $installed, Platform::Mysql));
	}

	public function testAUniqueKeyTheDatabaseNamedItselfCounts(): void {
		$online = new Table('online', array(new Column('user_id', 'INT(10) UNSIGNED', false, 1), new Column('ident', 'VARCHAR(200)', false, '')), array(), array('user_id_ident_idx' => array('user_id', 'ident')));
		$columns = array(new InstalledColumn('user_id', 'integer', false, '1'), new InstalledColumn('ident', 'character varying(200)', false, ''));

		$this->assertSame(array(), (new SchemaDiffer())->diff($online, new InstalledTable('online', $columns, array(), array(new InstalledIndex('user_id_ident_key', array('user_id', 'ident'), true))), Platform::Pgsql));
		$this->assertSame(array(), (new SchemaDiffer())->diff($online, new InstalledTable('online', $columns, array(), array(new InstalledIndex(null, array('user_id', 'ident'), true))), Platform::Sqlite));

		// A plain index over the same columns is no unique key
		$this->assertEquals(array(new AddIndex('online', 'user_id_ident_idx', array('user_id', 'ident'), true)), (new SchemaDiffer())->diff($online, new InstalledTable('online', $columns, array(), array(new InstalledIndex('other_idx', array('user_id', 'ident'), false))), Platform::Pgsql));
	}

	public function testAPlainIndexIsFoundByItsNameOnly(): void {
		$installed = self::mysqlUsers(indexes: array(new InstalledIndex('login_idx', array('username(8)'), false)));

		$this->assertEquals(array(new AddIndex('users', 'username_idx', array('username(8)'), false)), (new SchemaDiffer())->diff(self::users(), $installed, Platform::Mysql));
	}

	public function testWhatTheDeclarationDoesNotNameIsLeftAlone(): void {
		$installed = new InstalledTable('users', array_merge(array_values(self::mysqlUsers()->columns), array(new InstalledColumn('ext_karma', 'int', true, null))), array('id'), array(
			new InstalledIndex('username_idx', array('username(8)'), false),
			new InstalledIndex('ext_karma_idx', array('ext_karma'), false),
		));

		$this->assertSame(array(), (new SchemaDiffer())->diff(self::users(), $installed, Platform::Mysql));
	}

	public function testWhatAnEarlierReleaseHadIsDroppedWhereTheBoardStillHasIt(): void {
		$declared = self::users();
		$declared = new Table('users', $declared->columns, $declared->primaryKey, array(), $declared->indexes, removedColumns: array('save_pass', 'use_avatar'), removedIndexes: array('message_idx', 'user_id_idx'));

		$installed = self::mysqlUsers(array('save_pass' => new InstalledColumn('save_pass', 'tinyint(1)', false, '1')), array(new InstalledIndex('username_idx', array('username(8)'), false), new InstalledIndex('user_id_idx', array('id'), false)));

		$this->assertEquals(array(new DropColumn('users', 'save_pass'), new DropIndex('users', 'user_id_idx')), (new SchemaDiffer())->diff($declared, $installed, Platform::Mysql));
		$this->assertSame(array(), (new SchemaDiffer())->diff($declared, self::mysqlUsers(), Platform::Mysql), 'a board without them has nothing to drop');
	}

	public function testAPrimaryKeyIsMadeWithItsTableOnly(): void {
		$installed = new InstalledTable('users', array_values(self::mysqlUsers()->columns), array('username'), self::mysqlUsers()->indexes);

		$this->assertSame(array(), (new SchemaDiffer())->diff(self::users(), $installed, Platform::Mysql));
	}

	public function testAChangeSaysWhatItIs(): void {
		$this->assertSame(array(
			'create table users',
			'add column users.password VARCHAR(255)',
			'alter column users.last_post to INT(10) UNSIGNED NULL',
			'alter column users.show_sig to TINYINT(1) NOT NULL DEFAULT \'1\'',
			'add unique key online.user_id_ident_idx (user_id, ident(40))',
			'drop index online.ident_idx',
			'drop column users.save_pass',
		), array(
			(new CreateTable(self::users()))->describe(),
			(new AddColumn('users', self::users()->columns[2], 'username'))->describe(),
			(new AlterColumn('users', self::users()->columns[5]))->describe(),
			(new AlterColumn('users', self::users()->columns[3]))->describe(),
			(new AddIndex('online', 'user_id_ident_idx', array('user_id', 'ident(40)'), true))->describe(),
			(new DropIndex('online', 'ident_idx'))->describe(),
			(new DropColumn('users', 'save_pass'))->describe(),
		));
	}
}
