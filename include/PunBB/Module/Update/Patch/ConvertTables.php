<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Patch;

use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\SchemaInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Update\Api\BoardSettingsInterface;
use PunBB\Module\Update\Api\ConversionInterface;
use PunBB\Module\Update\Api\Data\TableColumnInterface;

/**
 * The text columns of a 1.2 board on MySQL, read as UTF-8 from here on: MySQL
 * converts the character set of each itself.
 *
 * It is the one patch that alters a column, since no declaration names the
 * character set a column had: it changes how stored bytes are read.
 */
final class ConvertTables implements DataPatchInterface {
	/** The tables 1.2 stored text in. */
	private const TABLES = array('bans', 'categories', 'censoring', 'config', 'extension_hooks', 'extensions', 'forum_perms', 'forums', 'groups', 'online', 'posts', 'ranks', 'reports', 'search_cache', 'search_matches', 'search_words', 'subscriptions', 'topics', 'users');

	/** MySQL's text types, and the binary type holding their bytes while the character set changes. */
	private const BINARY_TYPES = array('char' => 'binary', 'varchar' => 'varbinary', 'tinytext' => 'tinyblob', 'mediumtext' => 'mediumblob', 'text' => 'blob', 'longtext' => 'longblob');

	public function __construct(
		private readonly BoardSettingsInterface $settings,
		private readonly ConversionInterface $conversion,
		private readonly SchemaInterface $schema,
		private readonly Connection $db
	) {}

	public function apply(int $startAt): PatchStep {
		if (!BoardOptions::from12($this->settings) || $this->db->platform() !== Platform::Mysql)
			return new PatchStep();

		// The column a run stopped altering between its two statements, which reads as binary now
		$altering = explode(':', BoardOptions::of($this->settings)[BoardOptions::ALTERED_COLUMN] ?? '', 4);

		$lines = array();
		foreach (self::TABLES as $table)
		{
			$lines[] = sprintf('Converting table %s…', $this->db->prefix().$table);

			$this->conversion->setDefaultCharset($table);

			foreach ($this->conversion->columns($table) as $column)
			{
				$base = explode('(', $column->type())[0];
				if (count($altering) === 4 && $altering[0] === $table && $altering[1] === $column->name() && in_array($base, self::BINARY_TYPES, true))
				{
					$this->alterToUtf8($table, $column, $altering[3], $altering[2] === '1');
					continue;
				}

				$collation = $column->collation() ?? '';
				if (!isset(self::BINARY_TYPES[$base]) || str_contains($collation, 'utf8'))
					continue;

				$binaryCollation = str_ends_with($collation, '_bin');
				BoardOptions::store($this->settings, BoardOptions::ALTERED_COLUMN, $table.':'.$column->name().':'.(int) $binaryCollation.':'.$column->type());

				// Through the binary type, so the bytes are kept as they are and only read differently after; a binary collation stays binary
				$binary = (string) preg_replace('/'.$base.'/i', self::BINARY_TYPES[$base], $column->type());
				$this->schema->alterField($table, new Column($column->name(), $binary, $column->nullable(), $column->default()));
				$this->alterToUtf8($table, $column, $column->type(), $binaryCollation);
			}
		}

		return new PatchStep($lines);
	}

	private function alterToUtf8(string $table, TableColumnInterface $column, string $type, bool $binaryCollation): void {
		$this->schema->alterField($table, new Column($column->name(), $type.' CHARACTER SET utf8'.($binaryCollation ? ' COLLATE utf8_bin' : ''), $column->nullable(), $column->default()));
	}
}
