<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Platform;

/**
 * What the database reports about one of the forum's tables, read from its
 * catalogue: information_schema on MySQL, pg_catalog on PostgreSQL, the
 * table-valued pragmas on SQLite.
 */
final class SchemaReader {
	public function __construct(private readonly Connection $db) {}

	/** Table $name, unprefixed; null when the database has no such table. */
	public function table(string $name): ?InstalledTable {
		return match ($this->db->platform()) {
			Platform::Mysql		=> $this->mysql($name),
			Platform::Pgsql		=> $this->pgsql($name),
			Platform::Sqlite	=> $this->sqlite($name),
		};
	}

	private function mysql(string $name): ?InstalledTable {
		$table = $this->db->prefix().$name;

		$columns = array();
		// MariaDB quotes a literal default and reports a nullable column without one as NULL
		foreach ($this->db->select('SELECT COLUMN_NAME AS column_name, COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable, COLUMN_DEFAULT AS column_default, COLLATION_NAME AS collation_name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', $table) as $row)
			$columns[] = new InstalledColumn($row->string('column_name'), strtolower($row->string('column_type')), $row->string('is_nullable') === 'YES', self::literal($row->nullableString('column_default')), $row->nullableString('collation_name'));

		if ($columns === array())
			return null;

		$keys = array();
		foreach ($this->db->select('SELECT INDEX_NAME AS index_name, NON_UNIQUE AS non_unique, COLUMN_NAME AS column_name, SUB_PART AS sub_part FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX', $table) as $row)
		{
			$key = $row->string('index_name');
			$length = $row->nullableInt('sub_part');

			$keys[$key] ??= array('unique' => $row->int('non_unique') === 0, 'primary' => $key === 'PRIMARY', 'columns' => array());
			$keys[$key]['columns'][] = $row->string('column_name').($length !== null ? '('.$length.')' : '');
		}

		return self::assembled($name, $table, $columns, array(), $keys);
	}

	private function pgsql(string $name): ?InstalledTable {
		// The legacy pgsql layer creates tables and indexes unquoted, so PostgreSQL stores their names lowercased
		$table = strtolower($this->db->prefix().$name);

		$columns = array();
		foreach ($this->db->select('SELECT a.attname AS column_name, format_type(a.atttypid, a.atttypmod) AS column_type, CASE WHEN a.attnotnull THEN 0 ELSE 1 END AS is_nullable, pg_get_expr(d.adbin, d.adrelid) AS column_default FROM pg_attribute a INNER JOIN pg_class c ON c.oid = a.attrelid LEFT JOIN pg_attrdef d ON d.adrelid = a.attrelid AND d.adnum = a.attnum WHERE c.relname = ? AND c.relkind = \'r\' AND pg_table_is_visible(c.oid) AND a.attnum > 0 AND NOT a.attisdropped ORDER BY a.attnum', $table) as $row)
			$columns[] = new InstalledColumn($row->string('column_name'), strtolower($row->string('column_type')), $row->int('is_nullable') === 1, self::literal($row->nullableString('column_default')));

		if ($columns === array())
			return null;

		$keys = array();
		foreach ($this->db->select('SELECT i.relname AS index_name, CASE WHEN x.indisunique THEN 1 ELSE 0 END AS is_unique, CASE WHEN x.indisprimary THEN 1 ELSE 0 END AS is_primary, a.attname AS column_name FROM pg_index x INNER JOIN pg_class c ON c.oid = x.indrelid INNER JOIN pg_class i ON i.oid = x.indexrelid CROSS JOIN LATERAL unnest(x.indkey::int2[]) WITH ORDINALITY AS k(attnum, seq) INNER JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = k.attnum WHERE c.relname = ? AND c.relkind = \'r\' AND pg_table_is_visible(c.oid) ORDER BY i.relname, k.seq', $table) as $row)
		{
			$key = $row->string('index_name');

			$keys[$key] ??= array('unique' => $row->int('is_unique') === 1, 'primary' => $row->int('is_primary') === 1, 'columns' => array());
			$keys[$key]['columns'][] = $row->string('column_name');
		}

		return self::assembled($name, $table, $columns, array(), $keys);
	}

	private function sqlite(string $name): ?InstalledTable {
		$table = $this->db->prefix().$name;

		$columns = array();
		$primaryKey = array();
		foreach ($this->db->select('SELECT name, type, "notnull" AS not_null, dflt_value, pk FROM pragma_table_info(?) ORDER BY cid', $table) as $row)
		{
			$columns[] = new InstalledColumn($row->string('name'), strtolower($row->string('type')), $row->int('not_null') === 0, self::literal($row->nullableString('dflt_value')));

			if ($row->int('pk') > 0)
				$primaryKey[$row->int('pk')] = $row->string('name');
		}

		if ($columns === array())
			return null;

		ksort($primaryKey);

		$keys = array();
		foreach ($this->db->select('SELECT l.name AS index_name, l."unique" AS is_unique, l.origin AS origin, i.name AS column_name FROM pragma_index_list(?) AS l, pragma_index_info(l.name) AS i ORDER BY l.name, i.seqno', $table) as $row)
		{
			$key = $row->string('index_name');

			// The index SQLite keeps for a primary key that is not the rowid; table_info() reported the key
			if ($row->string('origin') === 'pk')
				continue;

			$keys[$key] ??= array('unique' => $row->int('is_unique') === 1, 'primary' => false, 'columns' => array());
			$keys[$key]['columns'][] = $row->string('column_name');
		}

		return self::assembled($name, $table, $columns, array_values($primaryKey), $keys);
	}

	/**
	 * @param list<InstalledColumn> $columns
	 * @param list<string> $primaryKey
	 * @param array<string, array{unique: bool, primary: bool, columns: list<string>}> $keys each index and key, by the name the database gave it
	 */
	private static function assembled(string $name, string $table, array $columns, array $primaryKey, array $keys): InstalledTable {
		$indexes = array();
		foreach ($keys as $key => $index)
		{
			if ($index['primary'])
			{
				$primaryKey = $index['columns'];
				continue;
			}

			// The drivers name an index <prefix><table>_<name>
			$named = str_starts_with(strtolower((string) $key), strtolower($table).'_');
			$indexes[] = new InstalledIndex($named ? substr((string) $key, strlen($table) + 1) : null, $index['columns'], $index['unique']);
		}

		return new InstalledTable($name, $columns, $primaryKey, $indexes);
	}

	/**
	 * A default as PostgreSQL, SQLite or MariaDB spell it, as a plain value:
	 * 'New Category' for 'New Category'::character varying. The sequence behind a SERIAL column
	 * is no default a table declares.
	 */
	private static function literal(?string $default): ?string {
		if ($default === null || str_starts_with($default, 'nextval('))
			return null;

		$default = (string) preg_replace('/::[a-z ]+$/i', '', $default);

		if (strtoupper($default) === 'NULL')
			return null;

		if (preg_match('/^\'((?:[^\']|\'\')*)\'$/s', $default, $matches) === 1)
			return str_replace('\'\'', '\'', $matches[1]);

		// PostgreSQL parenthesises a negative number
		return (string) preg_replace('/^\((.*)\)$/s', '$1', $default);
	}
}
