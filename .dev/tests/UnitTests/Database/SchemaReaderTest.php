<?php
/**
 * The reader over a MySQL catalogue: defaults as MySQL 8 and MariaDB report
 * them read as the same plain value.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Schema\SchemaReader;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\DriverInterface;
use PunBB\Module\Database\Sql\Platform;

class SchemaReaderTest extends TestCase {
	/** @return array<string, array{?string, ?string}> */
	public static function defaults(): array {
		return array(
			'MySQL 8 string'		=> array('English', 'English'),
			'MariaDB string'		=> array('\'English\'', 'English'),
			'MySQL 8 empty'			=> array('', ''),
			'MariaDB empty'			=> array('\'\'', ''),
			'MariaDB quote'			=> array('\'it\'\'s\'', 'it\'s'),
			'number'				=> array('0', '0'),
			'MySQL 8 no default'	=> array(null, null),
			'MariaDB no default'	=> array('NULL', null),
		);
	}

	#[DataProvider('defaults')]
	public function testADefaultIsReadAsItsPlainValue(?string $reported, ?string $read): void {
		$columns = array(array('column_name' => 'language', 'column_type' => 'varchar(25)', 'is_nullable' => 'YES', 'column_default' => $reported, 'collation_name' => 'utf8mb4_general_ci'));

		$table = (new SchemaReader(new Connection(new CatalogueMysqlDriver($columns), 'pun_')))->table('users');

		$this->assertSame($read, $table?->column('language')?->default);
	}
}

final class CatalogueMysqlDriver implements DriverInterface {
	/** @param list<array<string, int|float|string|null>> $columns */
	public function __construct(private readonly array $columns) {}

	public function platform(): Platform {
		return Platform::Mysql;
	}

	public function select(string $sql, array $parameters): array {
		return str_contains($sql, 'information_schema.COLUMNS') ? $this->columns : array();
	}

	public function execute(string $sql, array $parameters): int {
		return 0;
	}

	public function lastInsertId(): int {
		return 0;
	}
}
