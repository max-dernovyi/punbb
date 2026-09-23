<?php
/**
 * The declared schema is what the modules owning tables declare on their
 * module classes, in load order, and one table has one owner: the module that
 * writes it.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\DeclaredSchema;
use PunBB\Module\Database\Schema\SchemaException;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Schema\TableOwnerInterface;
use PunBB\Module\Database\Sql\DatabaseException;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\Wiring;

class DeclaredSchemaTest extends TestCase {
	/** @param list<string> $tables */
	private static function owner(string $name, array $tables): ModuleInterface {
		return new class($name, $tables) implements ModuleInterface, TableOwnerInterface {
			/** @param list<string> $tables */
			public function __construct(private string $name, private array $tables) {}

			public function name(): string { return $this->name; }

			public function dependencies(): array { return array(); }

			public function loadAfter(): array { return array(); }

			public function wire(Wiring $wiring): void {}

			public function tables(Platform $platform): array {
				return array_map(static fn (string $table): Table => new Table($table, array(new Column('id', $platform === Platform::Sqlite ? 'INTEGER' : 'SERIAL'))), $this->tables);
			}
		};
	}

	private static function bystander(): ModuleInterface {
		return new class() implements ModuleInterface {
			public function name(): string { return 'Bystander'; }

			public function dependencies(): array { return array(); }

			public function loadAfter(): array { return array(); }

			public function wire(Wiring $wiring): void {}
		};
	}

	public function testEveryOwnersTablesInModuleOrder(): void {
		$schema = new DeclaredSchema(self::owner('Topic', array('topics', 'posts')), self::bystander(), self::owner('Poll', array('polls')));

		$this->assertSame(array('topics', 'posts', 'polls'), array_map(static fn (Table $table): string => $table->name, $schema->tables(Platform::Mysql)));
		$this->assertSame('INTEGER', $schema->tables(Platform::Sqlite)[0]->columns[0]->type);
	}

	public function testATableHasOneOwner(): void {
		$this->expectException(SchemaException::class);
		$this->expectExceptionMessage('Modules Topic and Archive both declare table "posts"');

		(new DeclaredSchema(self::owner('Topic', array('topics', 'posts')), self::owner('Archive', array('posts'))))->tables(Platform::Pgsql);
	}

	/** @return array<string, array{Platform}> */
	public static function platforms(): array {
		return array('mysql' => array(Platform::Mysql), 'pgsql' => array(Platform::Pgsql), 'sqlite' => array(Platform::Sqlite));
	}

	private static function forum(): ModuleRegistry {
		return ModuleRegistry::discover(FORUM_ROOT.'include/PunBB/Module', 'PunBB\\Module\\');
	}

	#[DataProvider('platforms')]
	public function testTheForumsModulesDeclareEveryTableTheBoardHas(Platform $platform): void {
		$tables = array_map(static fn (Table $table): string => $table->name, self::forum()->container()->get(DeclaredSchema::class)->tables($platform));
		sort($tables);

		$this->assertSame(array(
			'bans', 'categories', 'censoring', 'config', 'data_patches', 'extension_hooks', 'extensions',
			'forum_perms', 'forum_subscriptions', 'forums', 'groups', 'online', 'posts',
			'ranks', 'reports', 'search_cache', 'search_matches', 'search_words',
			'subscriptions', 'topics', 'users',
		), $tables);
	}

	public function testEachTableIsDeclaredByTheModuleThatWritesIt(): void {
		$owners = array();
		foreach (self::forum()->modules() as $module)
			if ($module instanceof TableOwnerInterface)
				$owners[$module->name()] = array_map(static fn (Table $table): string => $table->name, $module->tables(Platform::Mysql));

		ksort($owners);

		$this->assertSame(array(
			'Bans'			=> array('bans'),
			'Categories'	=> array('categories'),
			'Censoring'		=> array('censoring'),
			'Database'		=> array('data_patches'),
			'Extensions'	=> array('extensions', 'extension_hooks'),
			'Forums'		=> array('forum_perms', 'forums'),
			'Groups'		=> array('groups'),
			'Misc'			=> array('subscriptions', 'forum_subscriptions'),
			'Post'			=> array('posts', 'topics'),
			'Ranks'			=> array('ranks'),
			'Reports'		=> array('reports'),
			'Search'		=> array('search_cache', 'search_matches', 'search_words'),
			'Settings'		=> array('config'),
			'Site'			=> array('online', 'users'),
		), $owners);
	}

	/** What 1.2 had and later releases dropped, dropped where an updated board still has it. */
	public function testEachTableNamesWhatAnEarlierReleaseHad(): void {
		$removed = array();
		foreach (self::forum()->container()->get(DeclaredSchema::class)->tables(Platform::Mysql) as $table)
			if ($table->removedColumns !== array() || $table->removedIndexes !== array())
				$removed[$table->name] = array($table->removedColumns, $table->removedIndexes);

		ksort($removed);

		$this->assertSame(array(
			'extensions'	=> array(array('uninstall_notes'), array()),
			'forums'		=> array(array('approval'), array()),
			'groups'		=> array(array('g_edit_subjects_interval', 'g_post_polls', 'g_posts_approved'), array()),
			'online'		=> array(array(), array('user_id_idx')),
			'posts'			=> array(array('approved'), array('message_idx')),
			'topics'		=> array(array(), array('subject_idx')),
			'users'			=> array(array('use_avatar', 'save_pass'), array()),
		), $removed);
	}

	public function testMysqlIndexesAPrefixAndSqliteKeysTheSearchWordsById(): void {
		$schema = self::forum()->container()->get(DeclaredSchema::class);

		$online = $schema->table('online', Platform::Mysql);
		$this->assertSame(array('user_id_ident_idx' => array('user_id', 'ident(40)')), $online->uniqueKeys);
		$this->assertSame('HEAP', $online->engine);
		$this->assertSame(array('username(8)'), $schema->table('users', Platform::Mysql)->indexes['username_idx']);
		$this->assertSame(array('ident'), $schema->table('search_cache', Platform::Pgsql)->indexes['ident_idx']);

		$words = $schema->table('search_words', Platform::Sqlite);
		$this->assertSame(array(array('id'), array('word_idx' => array('word'))), array($words->primaryKey, $words->uniqueKeys));
		$words = $schema->table('search_words', Platform::Pgsql);
		$this->assertSame(array(array('word'), array()), array($words->primaryKey, $words->uniqueKeys));
	}

	public function testATableNoModuleDeclaresIsAnError(): void {
		$this->expectException(SchemaException::class);
		$this->expectExceptionMessage('No module declares table "polls"');

		(new DeclaredSchema(self::owner('Topic', array('topics'))))->table('polls', Platform::Mysql);
	}

	public function testAPlatformIsTheDriverConfigPhpNames(): void {
		$this->assertSame(
			array(Platform::Mysql, Platform::Mysql, Platform::Pgsql, Platform::Sqlite),
			array_map(Platform::ofDbType(...), array('mysqli', 'mysqli_innodb', 'pgsql', 'sqlite3'))
		);

		$this->expectException(DatabaseException::class);
		$this->expectExceptionMessage('"sqlite" is not a database driver');
		Platform::ofDbType('sqlite');
	}
}
