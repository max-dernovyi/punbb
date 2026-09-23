<?php
/**
 * What the installer and the updater run on, over plain properties and a
 * journal of what they did: enough to run either with no PHP installation to
 * check, no files and no database.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PunBB\Module\Database\Patch\AppliedPatchesInterface;
use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\InstalledTable;
use PunBB\Module\Database\Schema\SchemaInterface;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Setup\Config\BoardConfiguration;
use PunBB\Module\Setup\Config\ConfigurationInterface;
use PunBB\Module\Setup\Database\DatabaseInterface;
use PunBB\Module\Setup\Database\DatabaseSettings;
use PunBB\Module\Setup\Environment\EnvironmentInterface;
use PunBB\Module\Setup\Files\BoardFilesInterface;

final class SetupJournal {
	/** @var list<string> */
	public array $entries = array();

	public function add(string $entry): void {
		$this->entries[] = $entry;
	}

	/** @return list<string> the entries starting with $prefix */
	public function starting(string $prefix): array {
		return array_values(array_filter($this->entries, static fn (string $entry): bool => str_starts_with($entry, $prefix)));
	}
}

final class FakeEnvironment implements EnvironmentInterface {
	/** @var list<string> */
	public array $errors = array();

	/** @var list<string> */
	public array $types = array('mysqli', 'mysqli_innodb', 'pgsql', 'sqlite3');

	public bool $uploads = true;

	public function requirementErrors(): array { return $this->errors; }

	public function version(): string { return '1.5.1'; }

	public function databaseRevision(): int { return 6; }

	public function databaseTypes(): array { return $this->types; }

	public function removedDatabaseReplacement(string $type): ?string { return array('mysql' => 'mysqli', 'sqlite' => 'sqlite3')[$type] ?? null; }

	public function acceptsUploads(): bool { return $this->uploads; }

	public function fetchesRemoteFiles(): bool { return false; }
}

final class FakeBoardFiles implements BoardFilesInterface {
	public bool $config = false;

	public ?string $written = null;

	public bool $writable = true;

	public bool $cache = true;

	/** @var array<string, ?array{int, int}> name => its size */
	public array $avatarFiles = array();

	/** @var list<string> */
	public array $languages = array('English');

	public function __construct(private readonly SetupJournal $journal) {}

	public function hasConfig(): bool { return $this->config; }

	public function writeConfig(string $contents): bool {
		$this->journal->add('write config');
		if ($this->writable)
			$this->written = $contents;

		return $this->writable;
	}

	public function replaceConfig(string $contents): bool {
		$this->journal->add('replace config');
		if ($this->writable)
			$this->written = $contents;

		return $this->writable;
	}

	public function cacheWritable(): bool { return $this->cache; }

	public function clearCache(): void { $this->journal->add('clear cache'); }

	public function avatarsWritable(): bool { return true; }

	public function avatars(): array { return array_keys($this->avatarFiles); }

	public function avatarSize(string $name): ?array { return $this->avatarFiles[$name] ?? null; }

	public function removeAvatar(string $name): void { $this->journal->add('remove avatar '.$name); }

	public function hasLanguage(string $language): bool { return in_array($language, $this->languages, true); }

	public function hasStyle(string $style): bool { return $style === 'Oxygen'; }
}

final class FakeSetupDatabase implements DatabaseInterface {
	public string $version = '8.4.0';

	public bool $innodb = true;

	public function __construct(private readonly SetupJournal $journal) {}

	public function open(DatabaseSettings $settings): void { $this->journal->add('open '.$settings->type.' '.$settings->name.' '.$settings->prefix); }

	public function openUnencoded(DatabaseSettings $settings): void { $this->journal->add('open unencoded '.$settings->type); }

	public function serverVersion(): string { return $this->version; }

	public function supportsInnodb(): bool { return $this->innodb; }

	public function setNames(string $charset): void { $this->journal->add('names '.$charset); }

	public function startTransaction(): void { $this->journal->add('start transaction'); }

	public function endTransaction(): void { $this->journal->add('end transaction'); }

	public function rollBack(): void { $this->journal->add('roll back'); }

	public function close(): void { $this->journal->add('close'); }
}

final class FakeSchema implements SchemaInterface {
	/** @var list<string> */
	public array $tables = array();

	/** @var list<string> "table.field" */
	public array $fields = array();

	/** @var list<string> "table.index" */
	public array $indexes = array();

	/** @var array<string, InstalledTable> what describe() reports, by table */
	public array $described = array();

	public function __construct(private readonly SetupJournal $journal) {}

	public function describe(string $table): ?InstalledTable { return $this->described[$table] ?? null; }

	public function tableExists(string $table): bool { return in_array($table, $this->tables, true); }

	public function fieldExists(string $table, string $field): bool { return in_array($table.'.'.$field, $this->fields, true); }

	public function indexExists(string $table, string $index): bool { return in_array($table.'.'.$index, $this->indexes, true); }

	public function createTable(Table $table): void { $this->journal->add('create '.$table->name); }

	public function addField(string $table, Column $column, ?string $after = null): void { $this->journal->add('add '.$table.'.'.$column->name.' '.$column->type.($after !== null ? ' after '.$after : '')); }

	public function alterField(string $table, Column $column, ?string $after = null): void { $this->journal->add('alter '.$table.'.'.$column->name.' '.$column->type); }

	public function dropField(string $table, string $field): void { $this->journal->add('drop '.$table.'.'.$field); }

	public function addIndex(string $table, string $index, array $columns, bool $unique = false): void { $this->journal->add('index '.$table.'.'.$index.' '.implode(',', $columns).($unique ? ' unique' : '')); }

	public function dropIndex(string $table, string $index): void { $this->journal->add('drop index '.$table.'.'.$index); }
}

final class FakeSetupConfiguration implements ConfigurationInterface {
	public ?BoardConfiguration $configuration;

	public function __construct() {
		$this->configuration = new BoardConfiguration(new DatabaseSettings('mysqli', 'db', 'forum', 'user', 'secret', 'pun_'), 'http://forum.test', 'forum_cookie');
	}

	public function load(): ?BoardConfiguration { return $this->configuration; }
}

final class JournalAppliedPatches implements AppliedPatchesInterface {
	/** @var list<string> */
	public array $names = array();

	public function __construct(private readonly SetupJournal $journal) {}

	public function names(): array { return $this->names; }

	public function record(string $name): void {
		$this->names[] = $name;
		$this->journal->add('record '.$name);
	}
}
