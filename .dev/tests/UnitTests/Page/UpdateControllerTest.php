<?php
/**
 * admin/db_update.php as a module, built with no forum: what it answers before
 * it opens the database, the checks that a board is one it updates, the start
 * form, the schema the start brings a board to, the data patches applied a
 * batch per request — a 1.4 board's and a 1.2 board's conversion — a patch
 * that fails, and the finish; each module brought up only when its recorded
 * version is behind, and a board that records none brought up whole.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\DeclaredPatches;
use PunBB\Module\Database\Patch\PatchApplier;
use PunBB\Module\Database\Patch\PatchDeclaration;
use PunBB\Module\Database\Patch\PatchException;
use PunBB\Module\Database\Patch\PatchOwnerInterface;
use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\DeclaredSchema;
use PunBB\Module\Database\Schema\InstalledColumn;
use PunBB\Module\Database\Schema\InstalledIndex;
use PunBB\Module\Database\Schema\InstalledTable;
use PunBB\Module\Database\Schema\SchemaInterface;
use PunBB\Module\Database\Schema\SchemaSynchronizer;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Schema\TableOwnerInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\DriverInterface;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Database\Version\InstalledVersion;
use PunBB\Module\Database\Version\ModuleUpgrade;
use PunBB\Module\Database\Version\ModuleVersions;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\ModuleTree;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Setup\Config\BoardConfiguration;
use PunBB\Module\Setup\Database\DatabaseInterface;
use PunBB\Module\Setup\Database\DatabaseSettings;
use PunBB\Module\Setup\Environment\EnvironmentInterface;
use PunBB\Module\Setup\Files\BoardFilesInterface;
use PunBB\Module\Setup\Page\SetupPage;
use PunBB\Module\Update\Api\BoardDataInterface;
use PunBB\Module\Update\Api\BoardSettingsInterface;
use PunBB\Module\Update\Api\ConversionInterface;
use PunBB\Module\Update\Api\Data\PostRangeInterface;
use PunBB\Module\Update\Api\Data\SettingInterface;
use PunBB\Module\Update\Api\Data\TextRowInterface;
use PunBB\Module\Update\Controller\Update;
use PunBB\Module\Update\Controller\UpdateController;
use PunBB\Module\Update\Model\PostRange;
use PunBB\Module\Update\Model\TableColumn;
use PunBB\Module\Update\Model\TextRow;
use PunBB\Module\Update\Parsing\PreparserInterface;

require_once __DIR__.'/SetupFakes.php';

final class FakeBoardSettings implements BoardSettingsInterface {
	/** @var array<string, ?string> */
	public array $config = array('o_cur_version' => '1.4.4', 'o_database_revision' => '4', 'o_board_title' => 'Board & co', 'o_default_style' => 'Oxygen', 'o_default_lang' => 'English', 'o_timeout_visit' => '600', 'o_redirect_delay' => '0');

	public function __construct(private readonly SetupJournal $journal) {}

	public function version(): ?string { return $this->config['o_cur_version'] ?? null; }

	public function all(): array {
		$settings = array();
		foreach ($this->config as $name => $value)
			$settings[] = new PunBB\Module\Update\Model\Setting($name, $value);

		return $settings;
	}

	public function add(SettingInterface ...$settings): void {
		foreach ($settings as $setting)
		{
			$this->journal->add('add setting '.$setting->name().'='.$setting->value());
			$this->config[$setting->name()] = $setting->value();
		}
	}

	public function update(SettingInterface ...$settings): void {
		foreach ($settings as $setting)
		{
			$this->journal->add('set '.$setting->name().'='.$setting->value());
			if (array_key_exists($setting->name(), $this->config))
				$this->config[$setting->name()] = $setting->value();
		}
	}

	public function replace(SettingInterface $setting, string $expected): void { $this->journal->add('replace '.$setting->name().' '.$expected.'='.$setting->value()); }

	public function rename(string $from, string $to): void { $this->journal->add('rename '.$from.' '.$to); }

	public function remove(string ...$names): void {
		$this->journal->add('remove setting '.implode(',', $names));
		foreach ($names as $name)
			unset($this->config[$name]);
	}
}

final class FakeBoardData implements BoardDataInterface {
	/** @var list<string> */
	public array $samples = array('Hello', 'Ünïcode');

	public bool $moderatorGroup = false;

	public bool $mailFails = false;

	/** The reorder step the connection is lost at. */
	public ?int $reorderFailsAt = null;

	public function __construct(private readonly SetupJournal $journal) {}

	public function spareGroupId(): int { return 5; }

	public function reorderGroups(int $spare, int $step): bool {
		if ($step > 12)
			return false;

		if ($step === $this->reorderFailsAt)
			throw new RuntimeException('Lost connection to the server');

		$this->journal->add('reorder groups '.$spare.':'.$step);

		return true;
	}

	public function hasModeratorGroup(): bool { return $this->moderatorGroup; }

	public function grantModerators(string $permission, int $value): void { $this->journal->add('grant '.$permission.'='.$value); }

	public function limitGroupMail(): void {
		if ($this->mailFails)
			throw new RuntimeException('the groups table is gone');

		$this->journal->add('limit group mail');
	}

	public function recordFirstPosts(): void { $this->journal->add('first posts'); }

	public function moveUnverifiedUsers(): void { $this->journal->add('unverified'); }

	public function supersededHotfixes(string $version): array { return array('hotfix_1_4_3'); }

	public function removeExtension(string $id): void { $this->journal->add('remove extension '.$id); }

	public function schemeLinkedinAddresses(): void { $this->journal->add('linkedin'); }

	public function storeAvatar(int $userId, int $type, int $width, int $height): void { $this->journal->add('avatar '.$userId.' '.$type.' '.$width.'x'.$height); }

	public function postRange(): PostRangeInterface { return new PostRange(1, 2, count($this->samples)); }

	public function postText(int $postId): ?string { return $this->samples[$postId - 1] ?? null; }

	public function forumIds(): array { return array(1, 2); }

	public function syncForum(int $forumId): void { $this->journal->add('sync '.$forumId); }

	public function emptySearchCache(): void { $this->journal->add('empty search cache'); }

	public function emptyOnline(): void { $this->journal->add('empty online'); }
}

final class FakeConversion implements ConversionInterface {
	/** @var array<string, list<TextRow>> */
	public array $rows = array();

	/** The rows stored before the connection is lost. */
	public int $storesLeft = PHP_INT_MAX;

	public function __construct(private readonly SetupJournal $journal) {}

	public function firstId(string $table): ?int { return ($this->rows[$table] ?? array()) !== array() ? $this->rows[$table][0]->id() : null; }

	public function nextId(string $table, int $id): ?int {
		foreach ($this->rows[$table] ?? array() as $row)
			if ($row->id() >= $id)
				return $row->id();

		return null;
	}

	public function rows(string $table, string $idColumn, array $columns, ?int $from = null, ?int $to = null): array {
		return array_values(array_filter($this->rows[$table] ?? array(), static fn (TextRow $row): bool => $from === null || ($row->id() >= $from && $row->id() < (int) $to)));
	}

	public function store(string $table, string $idColumn, TextRowInterface $row): void {
		$values = array();
		foreach ($row->columns() as $column)
			$values[] = $column.'='.var_export($row->value($column), true);

		if ($this->storesLeft-- === 0)
			throw new \RuntimeException('Lost connection to the server');

		$this->journal->add('store '.$table.' '.$row->id().' '.implode(' ', $values));

		foreach ($this->rows[$table] ?? array() as $i => $stored)
			if ($stored->id() === $row->id())
				$this->rows[$table][$i] = $row;
	}

	/** @var array<string, list<TableColumn>> the columns a table is described with instead of the defaults */
	public array $columns = array();

	public function columns(string $table): array {
		if (isset($this->columns[$table]))
			return $this->columns[$table];

		return $table === 'search_words' ? array(new TableColumn('word', 'varchar(20)', 'latin1_bin', false, '')) : array(new TableColumn('title', 'varchar(50)', 'latin1_swedish_ci', true, null), new TableColumn('id', 'int(10) unsigned', null, false, null));
	}

	public function setDefaultCharset(string $table): void { $this->journal->add('charset '.$table); }
}

final class FakePreparser implements PreparserInterface {
	public function preparse(string $text, bool $signature): string { return strtolower($text).($signature ? ' (sig)' : ''); }
}

/** A MySQL connection that runs nothing: the patches ask it only for its platform and prefix. */
final class IdleMysqlDriver implements DriverInterface {
	public function platform(): Platform { return Platform::Mysql; }

	public function select(string $sql, array $parameters): array { return array(); }

	public function execute(string $sql, array $parameters): int { return 0; }

	public function lastInsertId(): int { return 0; }
}

class UpdateControllerTest extends TestCase {
	private const PATCHES = array(
		'Update::avatars', 'Update::options', 'Update::moderator_groups', 'Update::group_mail', 'Update::first_posts', 'Update::unverified_users', 'Update::linkedin_addresses',
		'Update::convert_misc', 'Update::convert_reports', 'Update::convert_search_words', 'Update::convert_users', 'Update::convert_topics', 'Update::convert_posts', 'Update::convert_tables', 'Update::preparse_posts', 'Update::preparse_signatures',
	);

	private SetupJournal $journal;

	private FakeEnvironment $environment;

	private FakeSetupConfiguration $configuration;

	private FakeBoardFiles $files;

	private FakeSchema $schema;

	private FakeBoardSettings $settings;

	private FakeBoardData $data;

	private FakeConversion $conversion;

	private JournalAppliedPatches $applied;

	private JournalInstalledVersions $versions;

	/** @var array<string, string> module => its declared version */
	private array $declared = array();

	private UpdateController $controller;

	/** @var Closure(list<ModuleInterface>): UpdateController the updater of these modules */
	private Closure $updater;

	protected function setUp(): void {
		$this->journal = new SetupJournal();
		$this->environment = new FakeEnvironment();
		$this->configuration = new FakeSetupConfiguration();
		$this->files = new FakeBoardFiles($this->journal);
		$this->schema = new FakeSchema($this->journal);
		$this->schema->tables = array('config');
		$this->settings = new FakeBoardSettings($this->journal);
		$this->data = new FakeBoardData($this->journal);
		$this->conversion = new FakeConversion($this->journal);
		$this->applied = new JournalAppliedPatches($this->journal);
		$this->versions = new JournalInstalledVersions($this->journal);
		$database = new FakeSetupDatabase($this->journal);
		$pages = new SetupPage(new TemplateRenderer());
		$modules = ModuleRegistry::discover(ModuleTree::core(FORUM_ROOT))->modules();

		foreach ($modules as $module)
			$this->declared[$module->name()] = $module->version();

		// What the Update module's patches are built from
		$container = new Container(array(
			BoardSettingsInterface::class	=> fn (): object => $this->settings,
			BoardDataInterface::class		=> fn (): object => $this->data,
			ConversionInterface::class		=> fn (): object => $this->conversion,
			SchemaInterface::class			=> fn (): object => $this->schema,
			DatabaseInterface::class		=> fn (): object => $database,
			EnvironmentInterface::class		=> fn (): object => $this->environment,
			BoardFilesInterface::class		=> fn (): object => $this->files,
			PreparserInterface::class		=> fn (): object => new FakePreparser(),
			Connection::class				=> fn (): object => new Connection(new IdleMysqlDriver(), 'pun_'),
		));

		$this->updater = fn (array $modules): UpdateController => new UpdateController($this->environment, $this->configuration, $database, $pages,
			fn (): Update => new Update($this->settings, $this->data, $this->schema, $database, $this->environment, $this->files, $pages, new TemplateRenderer(), new ModuleUpgrade(
				new ModuleVersions($this->versions, ...$modules),
				new SchemaSynchronizer(new DeclaredSchema(...$modules), $this->schema),
				new PatchApplier(new DeclaredPatches(...$modules), $this->applied, $container)
			)));
		$this->controller = ($this->updater)($modules);
	}

	/**
	 * A board at this release: every patch applied, every module recorded at its version but one in $behind.
	 *
	 * @param array<string, InstalledVersion> $behind module => what the board records for it
	 */
	private function atRelease(array $behind = array()): void {
		$this->settings->config['o_cur_version'] = '1.5.1';
		$this->settings->config['o_database_revision'] = '6';
		$this->applied->names = self::PATCHES;

		foreach ($this->declared as $module => $version)
			$this->versions->versions[$module] = $behind[$module] ?? new InstalledVersion($version, $version);
	}

	/** @param array<string, string> $query */
	private function get(array $query = array()): Response {
		return $this->controller->handle(new Request('GET', '/', 'admin/db_update.php', $query));
	}

	/** Every patch before $patch recorded, as a board has them that stopped there. */
	private function appliedUpTo(string $patch): void {
		$this->applied->names = array_slice(self::PATCHES, 0, (int) array_search($patch, self::PATCHES, true));
	}

	/** @return array<string, string> the query the page sends the browser on to */
	private static function next(string $body): array {
		if (preg_match('/window\.location="db_update\.php\?([^"]*)"/', $body, $matches) !== 1)
			return array();

		parse_str(str_replace('\u0026', '&', $matches[1]), $query);

		return array_map(strval(...), $query);
	}

	/** @return list<string> the pages, from the patch stage on until it sends the browser to the finish */
	private function applyAll(): array {
		$bodies = array();
		$query = array('stage' => 'patch');

		while (($query['stage'] ?? '') === 'patch' && count($bodies) < 100)
		{
			$bodies[] = $body = $this->get($query)->body;
			$query = self::next($body);
		}

		$this->assertSame(array('stage' => 'finish'), $query, 'the patches go on to the finish');

		return $bodies;
	}

	/** A patch request the simulated lost connection stops. */
	private function assertConnectionLost(): void {
		try {
			$this->get(array('stage' => 'patch'));
			$this->fail('the lost connection stops the update');
		}
		catch (PatchException $e) {
			$this->assertInstanceOf(RuntimeException::class, $e->getPrevious());
			$this->assertSame('Lost connection to the server', $e->getPrevious()->getMessage());
		}
	}

	public function testWithoutConfigPhpNothingIsOpened(): void {
		$this->configuration->configuration = null;

		$this->assertSame('Cannot find config.php, are you sure it exists?', $this->get()->body);
		$this->assertSame(array(), $this->journal->entries);
	}

	public function testARemovedDriverIsNamedWithItsReplacementBeforeTheDatabaseIsOpened(): void {
		$this->configuration->configuration = new BoardConfiguration(new DatabaseSettings('mysql', '', 'forum', '', '', ''), null, 'forum_cookie');

		$this->assertSame('Your config.php uses the \'mysql\' database driver, which was removed along with the PHP extension it needs. Set $db_type to \'mysqli\' in config.php and run this script again.', $this->get()->body);
		$this->assertSame(array(), $this->journal->entries);
	}

	public function testAPhpInstallationLackingARequirementIsToldWhat(): void {
		$this->environment->errors = array('No database.');

		$this->assertSame("PunBB cannot be updated on this PHP installation:\n<ul><li>No database.</li></ul>", $this->get()->body);
	}

	public function testADatabaseWithoutABoardOrWithTooOldABoardIsAVersionMismatch(): void {
		$this->schema->tables = array();
		$response = $this->get();

		$this->assertSame(503, $response->status);
		$this->assertStringContainsString('<title>Error - PunBB</title>', $response->body);
		$this->assertStringContainsString('<p>Version mismatch. The database \'forum\' doesn\'t seem to be running a PunBB database schema supported by this update script.</p>', $response->body);
		$this->assertSame(array('open unencoded mysqli', 'start transaction', 'end transaction', 'close'), $this->journal->entries);

		$this->schema->tables = array('config');
		$this->settings->config['o_cur_version'] = '1.1.5';
		$this->assertStringContainsString('Version mismatch.', $this->get()->body);
	}

	public function testAnUpToDateBoardIsToldSoUnderItsTitle(): void {
		$this->atRelease();

		$response = $this->get();

		$this->assertSame(503, $response->status);
		$this->assertStringContainsString('<title>Error - Board &amp; co</title>', $response->body);
		$this->assertStringContainsString("\t<h1>Sorry! The page could not be loaded.</h1>\n<p>Your database is already as up-to-date as this script can make it.</p>\n</body>\n</html>\n", $response->body);
	}

	/** The release and the revision current, the board is still updated while a module is behind or none is recorded at all. */
	public function testABoardAtTheReleaseWithAModuleBehindIsOfferedTheUpdate(): void {
		$this->atRelease(array('Ranks' => new InstalledVersion('1.4.0', '1.3.0')));
		$this->assertStringContainsString('value="Start update"', $this->get()->body, 'data behind');

		$this->atRelease(array('Ranks' => new InstalledVersion('1.3.0', '1.4.0')));
		$this->assertStringContainsString('value="Start update"', $this->get()->body, 'schema behind');

		$this->atRelease();
		$this->versions->versions = array();
		$this->assertStringContainsString('value="Start update"', $this->get()->body, 'a board from before module versions');
	}

	/** Below the revision a board is from before 2.0, whatever it records of its modules. */
	public function testABoardBelowTheRevisionIsUpdatedWithEveryModuleRecorded(): void {
		$this->atRelease();
		$this->settings->config['o_database_revision'] = '5';

		$this->assertStringContainsString('value="Start update"', $this->get()->body);

		unset($this->settings->config['o_database_revision']);
		$this->assertStringContainsString('value="Start update"', $this->get()->body, 'a board older than the revision itself');
	}

	public function testTheFormOffersTheConversionOnlyToA12Board(): void {
		$body = $this->get()->body;

		$this->assertStringContainsString('href="http://forum.test/style/Oxygen/Oxygen.css"', $body);
		$this->assertStringContainsString('<input type="hidden" name="stage" value="start" />', $body);
		$this->assertStringNotContainsString('req_old_charset', $body);
		$this->assertContains('names utf8', $this->journal->entries);

		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->data->samples = array("Caf\xE9");
		$body = $this->get()->body;
		$this->assertStringContainsString('name="req_old_charset" size="12" maxlength="20" value="ISO-8859-1"', $body);
		$this->assertContains('names latin1', $this->journal->entries);

		$this->data->samples = array('Café', 'Cafés');
		$this->assertStringContainsString('<a href="db_update.php?force=1">force the conversion to run</a>', $this->get()->body);
		$this->assertStringContainsString('name="req_old_charset"', $this->get(array('force' => '1'))->body);
	}

	public function testAStyleOrALanguageTheBoardLostIsReplaced(): void {
		$this->settings->config['o_default_style'] = 'Gone';
		$this->settings->config['o_default_lang'] = 'Gone';

		$this->get();

		$this->assertSame(array('set o_default_style=Oxygen', 'set o_default_lang=English'), $this->journal->starting('set o_default'));
	}

	public function testTheStartBringsTheSchemaToWhatTheModulesDeclareAndGoesOnToThePatches(): void {
		// A 1.4 board's online table, with the index 1.2 put back
		$this->schema->described['online'] = new InstalledTable('online', array(
			new InstalledColumn('user_id', 'int unsigned', false, '1'),
			new InstalledColumn('ident', 'varchar(200)', false, ''),
			new InstalledColumn('logged', 'int unsigned', false, '0'),
			new InstalledColumn('idle', 'tinyint(1)', false, '0'),
			new InstalledColumn('csrf_token', 'varchar(40)', false, ''),
			new InstalledColumn('prev_url', 'varchar(255)', true, null),
			new InstalledColumn('last_post', 'int unsigned', true, null),
			new InstalledColumn('last_search', 'int unsigned', true, null),
		), array(), array(
			new InstalledIndex('user_id_ident_idx', array('user_id', 'ident(40)'), true),
			new InstalledIndex('ident_idx', array('ident(40)'), false),
			new InstalledIndex('logged_idx', array('logged'), false),
			new InstalledIndex('user_id_idx', array('user_id'), false),
		));

		$body = $this->get(array('stage' => 'start'))->body;

		$this->assertStringStartsWith("Create table data_patches…<br />\nCreate table modules…<br />\nDrop index online.user_id_idx…<br />\nCreate table users…<br />", $body);
		$this->assertStringContainsString('<script type="text/javascript">window.location="db_update.php?stage=patch"</script><br />JavaScript seems to be disabled. <a href="db_update.php?stage=patch">Click here to continue</a>.', $body);
		$this->assertCount(21, $this->journal->starting('create'), 'every table but the one the board has');
		$this->assertLessThan(array_search('create data_patches', $this->journal->entries, true), array_search('empty online', $this->journal->entries, true), 'the online list is empty before a key over it is added');
		$this->assertSame(array(), $this->journal->starting('record'), 'no patch is applied yet');
		$this->assertSame(array(), $this->journal->starting('remove setting'), 'a 1.4 board has no text to convert');
		$this->assertSame(array('remove extension hotfix_1_4_3'), $this->journal->starting('remove extension'));

		// A board from before module versions records none: every module's tables are brought up, and recorded once they are
		$this->assertSame(array_map(static fn (string $module, string $version): string => 'version '.$module.' schema '.$version, array_keys($this->declared), $this->declared), $this->journal->starting('version'), 'every module\'s schema, in load order, and no data yet');
		$this->assertGreaterThan(array_search('create users', $this->journal->entries, true), array_search('version Framework schema 2.0.0', $this->journal->entries, true), 'recorded once the table recording it is created');
	}

	public function testTheStartSynchronizesOnlyTheTablesOfAModuleBehind(): void {
		$this->atRelease(array('Ranks' => new InstalledVersion('1.3.0', '1.3.0'), 'Reports' => new InstalledVersion('1.4.0', '1.3.0')));

		$body = $this->get(array('stage' => 'start'))->body;

		// The fake database has no table at all: every one would be created, were its module brought up
		$this->assertStringStartsWith("Create table ranks…<br />\n<script", $body);
		$this->assertSame(array('create ranks'), $this->journal->starting('create'), 'not Reports\', whose schema is current, nor any other');
		$this->assertSame(array('version Ranks schema 1.4.0'), $this->journal->starting('version'), 'the data waits for the patches');
		$this->assertSame(array('stage' => 'finish'), self::next($body), 'neither declares a patch');

		$this->get(array('stage' => 'finish'));

		$this->assertSame(array('version Ranks schema 1.4.0', 'version Ranks data 1.4.0', 'version Reports data 1.4.0'), $this->journal->starting('version'));
		$this->assertSame(array(), $this->journal->starting('record'));
	}

	/** Its schema brought up by the start, no module is behind; the update under way still reaches its finish, and only then is the board up to date. */
	public function testABoardWhoseModulesTheStartBroughtUpIsLetOnToTheFinish(): void {
		$this->atRelease(array('Reports' => new InstalledVersion('1.3.0', '1.4.0')));

		$this->assertSame(array('stage' => 'finish'), self::next($this->get(array('stage' => 'start'))->body));
		$this->assertSame(array('add setting update:under_way=1'), $this->journal->starting('add setting'));
		$this->assertSame(array('version Reports schema 1.4.0'), $this->journal->starting('version'));

		$this->assertStringContainsString('PunBB Database Update completed!', $this->get(array('stage' => 'finish'))->body);
		$this->assertSame(array('set o_cur_version=1.5.1', 'set o_database_revision=6', 'remove setting update:under_way'), array_slice(array_values(array_filter($this->journal->entries, static fn (string $entry): bool => preg_match('/^(set o_|remove setting update:under_way)/', $entry) === 1)), -3), 'the mark goes last');
		$this->assertStringContainsString('already as up-to-date', $this->get(array('stage' => 'finish'))->body);
	}

	public function testOnlyThePatchesOfAModuleWhoseDataIsBehindAreApplied(): void {
		$this->atRelease();
		$this->settings->config['o_cur_version'] = '1.4.4';
		$this->applied->names = array();

		$this->assertSame(array('stage' => 'finish'), self::next($this->get(array('stage' => 'start'))->body), 'the Update module\'s data is current, so its patches are not looked for');
		$this->assertSame(array(), $this->journal->starting('record'));
		$this->assertSame(array(), $this->journal->starting('version'));
	}

	/** @return array<string, array{list<Table>, list<PatchDeclaration>, string}> */
	public static function clashingModuleProvider(): array {
		return array(
			'a table another module declares'	=> array(array(new Table('users', array(new Column('id', 'SERIAL')), array('id'))), array(), 'Modules Site and Clashing both declare table &quot;users&quot;. Remove the module at fault from modules/ and run the update again.'),
			'a patch named for another module'	=> array(array(), array(new PatchDeclaration('Site::clash', array(), static fn (): DataPatchInterface => throw new LogicException('never built'))), 'Module Clashing declares data patch &quot;Site::clash&quot;; a patch is named Clashing::&lt;lowercase_name&gt;, at most 150 characters. Remove the module at fault from modules/ and run the update again.'),
		);
	}

	/**
	 * A third-party module whose declarations do not fit the forum's is named on the page, and nothing is changed for it.
	 *
	 * @param list<Table> $tables
	 * @param list<PatchDeclaration> $patches
	 */
	#[DataProvider('clashingModuleProvider')]
	public function testAModuleClashingWithAnotherIsNamedForTheOneRunningTheUpdate(array $tables, array $patches, string $message): void {
		$modules = ModuleRegistry::discover(ModuleTree::core(FORUM_ROOT))->modules();
		$modules[] = new class ($tables, $patches) implements ModuleInterface, TableOwnerInterface, PatchOwnerInterface {
			/**
			 * @param list<Table> $tables
			 * @param list<PatchDeclaration> $patches
			 */
			public function __construct(private readonly array $tables, private readonly array $patches) {}

			public function name(): string { return 'Clashing'; }

			public function dependencies(): array { return array(); }

			public function loadAfter(): array { return array(); }

			public function version(): string { return '1.0.0'; }

			public function wire(Wiring $wiring): void {}

			public function tables(Platform $platform): array { return $this->tables; }

			public function patches(): array { return $this->patches; }
		};
		$this->controller = ($this->updater)($modules);
		$this->atRelease();

		$body = $this->get(array('stage' => 'start'))->body;

		$this->assertStringContainsString($message, $body);
		$this->assertSame(array(), $this->journal->starting('create'));
		$this->assertNotContains('version Clashing data 1.0.0', $this->journal->entries);
		$this->assertNotContains('add setting update:under_way=1', $this->journal->entries, 'a failed start leaves the update closed once the module is removed');
	}

	public function testThe12TextsCharacterSetIsKeptForThePatches(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';

		$this->get(array('stage' => 'start', 'convert_charset' => '1', 'req_old_charset' => 'iso8859-15'));
		$this->assertSame(array('remove setting update:charset', 'add setting update:charset=ISO-8859-15'), array_values(array_filter($this->journal->entries, static fn (string $entry): bool => str_contains($entry, 'update:charset'))));

		$this->journal->entries = array();
		$this->get(array('stage' => 'start'));
		$this->assertSame(array('remove setting update:charset'), array_values(array_filter($this->journal->entries, static fn (string $entry): bool => str_contains($entry, 'update:charset'))), 'without the conversion there is none');
	}

	public function testAnUnknownCharacterSetIsRefused(): void {
		$this->assertSame('Unknown character set. Set req_old_charset to an encoding this PHP installation supports.', $this->get(array('stage' => 'start', 'req_old_charset' => 'NO-SUCH-SET'))->body);
	}

	public function testEachRequestAppliesTheFirstPatchTheBoardHasNotRecorded(): void {
		$body = $this->get(array('stage' => 'patch'))->body;

		$this->assertStringStartsWith("Applying Update::avatars…<br />\n<script", $body);
		$this->assertSame(array('stage' => 'patch'), self::next($body));
		$this->assertSame(array('record Update::avatars'), $this->journal->starting('record'));

		$this->assertStringStartsWith("Applying Update::options…<br />\n<script", $this->get(array('stage' => 'patch'))->body);
	}

	public function testA14BoardsPatchesChangeWhatA14BoardLacks(): void {
		$this->files->avatarFiles = array('3.png' => array(60, 60), '4.jpg' => array(100, 60), '1.gif' => array(1, 1), 'x.png' => array(1, 1), '5.gif' => null);
		$this->settings->config += array('o_avatars_width' => '60', 'o_avatars_height' => '60');

		$this->assertCount(16, $this->applyAll());

		$this->assertSame(array_map(static fn (string $patch): string => 'record '.$patch, self::PATCHES), $this->journal->starting('record'));
		$this->assertSame(array('version Update data 2.0.0'), $this->journal->starting('version'), 'the one module declaring patches, once they are all applied');
		$this->assertSame(array('record Update::preparse_signatures', 'version Update data 2.0.0'), array_slice(array_values(array_filter($this->journal->entries, static fn (string $entry): bool => preg_match('/^(record|version) /', $entry) === 1)), -2));
		$this->assertSame(array('avatar 3 3 60x60'), $this->journal->starting('avatar'));
		$this->assertSame(array('remove avatar 4.jpg', 'remove avatar 5.gif'), $this->journal->starting('remove avatar'));
		$this->assertContains('add setting o_sef=Default', $this->journal->entries);
		$this->assertContains('rename o_server_timezone o_default_timezone', $this->journal->entries);
		$this->assertContains('set o_timeout_visit=1800', $this->journal->entries);
		$this->assertContains('limit group mail', $this->journal->entries);
		$this->assertContains('unverified', $this->journal->entries);
		$this->assertNotContains('reorder groups', $this->journal->entries, 'a 1.4 board has its moderator group');
		$this->assertNotContains('first posts', $this->journal->entries);
		$this->assertNotContains('linkedin', $this->journal->entries, 'only a board between 1.3 and 1.4.1 stored them');
		$this->assertSame(array(), $this->journal->starting('store'), 'a 1.4 board\'s text is UTF-8');
		$this->assertSame(array(), $this->journal->starting('alter'));
	}

	public function testA12BoardsModeratorsBecomeAGroupAndItsOptionsPermissions(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['p_mod_rename_users'] = '1';
		$this->appliedUpTo('Update::moderator_groups');

		$this->get(array('stage' => 'patch'));

		$this->assertSame(array('add setting update:groups=5:0', 'reorder groups 5:0', 'set update:groups=5:1', 'reorder groups 5:1'), array_slice($this->journal->entries, 3, 4), 'the spare group is recorded before the first step');
		$this->assertSame(13, count($this->journal->starting('reorder groups')));
		$this->assertSame(array('set update:groups=5:13', 'replace o_default_user_group 4=3', 'grant g_mod_rename_users=1', 'remove setting p_mod_rename_users'), array_slice($this->journal->entries, -7, 4));

		// Applied again after a failure, it does not move the groups back
		$this->journal->entries = array();
		$this->data->moderatorGroup = true;
		$this->settings->config['p_mod_rename_users'] = '1';
		$this->appliedUpTo('Update::moderator_groups');
		$this->get(array('stage' => 'patch'));

		$this->assertSame(array(), $this->journal->starting('reorder groups'));
		$this->assertContains('grant g_mod_rename_users=1', $this->journal->entries);
	}

	public function testAGroupReorderInterruptedPartWayResumesAtTheStepItStoppedAt(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->appliedUpTo('Update::moderator_groups');
		$this->data->reorderFailsAt = 6;

		$this->assertConnectionLost();

		// The first step made group 2 moderate, which MyISAM keeps
		$this->journal->entries = array();
		$this->data->moderatorGroup = true;
		$this->data->reorderFailsAt = null;
		$this->get(array('stage' => 'patch'));

		$this->assertSame(array('reorder groups 5:6', 'reorder groups 5:7', 'reorder groups 5:8', 'reorder groups 5:9', 'reorder groups 5:10', 'reorder groups 5:11', 'reorder groups 5:12'), $this->journal->starting('reorder groups'));
		$this->assertContains('replace o_default_user_group 4=3', $this->journal->entries);
	}

	public function testAGroupReorderInterruptedAtItsSecondStepResumesThere(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->appliedUpTo('Update::moderator_groups');
		$this->data->reorderFailsAt = 1;

		$this->assertConnectionLost();
		$this->assertSame('5:1', $this->settings->config['update:groups']);

		// Step 0 made group 2 moderate, so the board reads as having its moderator group already
		$this->journal->entries = array();
		$this->data->moderatorGroup = true;
		$this->data->reorderFailsAt = null;
		$this->get(array('stage' => 'patch'));

		$this->assertSame(array_map(static fn (int $step): string => 'reorder groups 5:'.$step, range(1, 12)), $this->journal->starting('reorder groups'));
		$this->assertSame(array(), $this->journal->starting('add setting update:groups'), 'the spare group is not picked again');
		$this->assertContains('replace o_default_user_group 4=3', $this->journal->entries);
	}

	public function testAConversionPatchConvertsABatchAndGoesOnFromWhereItStopped(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['update:charset'] = 'ISO-8859-1';
		$this->appliedUpTo('Update::convert_users');
		$this->conversion->rows['users'] = array(
			new TextRow(2, array('username' => "J\xF6rg", 'title' => '', 'realname' => null, 'location' => "K&ouml;ln", 'signature' => '&#8364;', 'admin_note' => null)),
			new TextRow(3, array('username' => 'plain', 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null)),
			new TextRow(400, array('username' => "S\xF8ren", 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null)),
		);

		$body = $this->get(array('stage' => 'patch'))->body;

		$this->assertStringStartsWith("Applying Update::convert_users…<br />\nConverting user 2…<br />\nConverting user 3…<br />\n<script", $body);
		$this->assertSame(array("store users 2 username='Jörg' title=NULL realname=NULL location='Köln' signature='€' admin_note=NULL"), $this->journal->starting('store'));
		$this->assertStringContainsString('window.location="db_update.php?stage=patch\u0026patch=Update%3A%3Aconvert_users\u0026start_at=400"', $body);
		$this->assertSame(array(), $this->journal->starting('record'), 'a patch with rows left is not recorded');

		$body = $this->get(self::next($body))->body;

		$this->assertStringStartsWith("Applying Update::convert_users…<br />\nConverting user 400…<br />\n<script", $body);
		$this->assertSame(array('stage' => 'patch'), self::next($body));
		$this->assertSame(array('record Update::convert_users'), $this->journal->starting('record'));
		$this->assertSame('users:700', $this->settings->config['update:converted'], 'a run stopped before the record converts nothing again');
	}

	public function testAConversionRunAgainGoesOnAfterTheBatchesItStored(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['update:charset'] = 'ISO-8859-1';
		$this->appliedUpTo('Update::convert_users');
		$this->conversion->rows['users'] = array(
			new TextRow(2, array('username' => '&amp;amp;', 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null)),
			new TextRow(400, array('username' => "S\xF8ren", 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null)),
		);

		$this->get(array('stage' => 'patch'));
		$this->assertSame('users:400', $this->settings->config['update:converted']);

		// The redirect lost, the update is run again from its first batch
		$this->journal->entries = array();
		$this->assertStringStartsWith("Applying Update::convert_users…<br />\nConverting user 400…<br />", $this->get(array('stage' => 'patch'))->body);
		$this->assertSame(array("store users 400 username='Søren' title=NULL realname=NULL location=NULL signature=NULL admin_note=NULL"), $this->journal->starting('store'), 'a stored row is not decoded twice');
	}

	public function testABatchInterruptedPartWayStoresItsRemainingRowsOnly(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['update:charset'] = 'ISO-8859-1';
		$this->appliedUpTo('Update::convert_users');
		$this->conversion->rows['users'] = array(
			new TextRow(2, array('username' => '&amp;amp;', 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null)),
			new TextRow(3, array('username' => "J\xF6rg", 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null)),
		);
		$this->conversion->storesLeft = 1;

		$this->assertConnectionLost();

		$this->journal->entries = array();
		$this->conversion->storesLeft = PHP_INT_MAX;
		$this->get(array('stage' => 'patch'));

		$this->assertSame(array("store users 3 username='Jörg' title=NULL realname=NULL location=NULL signature=NULL admin_note=NULL"), $this->journal->starting('store'), 'a stored row is not decoded twice');
		$this->assertSame('&amp;', $this->conversion->rows['users'][0]->value('username'));
	}

	public function testARowTheDatabaseCoercedOnStoreIsNotConvertedAgain(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['update:charset'] = 'ISO-8859-1';
		$this->appliedUpTo('Update::convert_users');
		$this->conversion->rows['users'] = array(
			new TextRow(2, array('username' => '&#x1F642; &amp;amp;', 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null)),
			new TextRow(3, array('username' => "J\xF6rg", 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null)),
		);
		$this->conversion->storesLeft = 1;

		$this->assertConnectionLost();

		// Non-strict utf8mb3 replaces the character it cannot hold
		$this->conversion->rows['users'][0] = new TextRow(2, array('username' => '? &amp;', 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null));
		$this->journal->entries = array();
		$this->conversion->storesLeft = PHP_INT_MAX;
		$this->get(array('stage' => 'patch'));

		$this->assertSame(array("store users 3 username='Jörg' title=NULL realname=NULL location=NULL signature=NULL admin_note=NULL"), $this->journal->starting('store'), 'a coerced row is not decoded twice');
	}

	public function testABatchWithTextTheCharsetCannotConvertStoresNoRow(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['update:charset'] = 'NO-SUCH-SET';
		$this->appliedUpTo('Update::convert_users');
		$this->conversion->rows['users'] = array(
			new TextRow(2, array('username' => '&ouml;', 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null)),
			new TextRow(3, array('username' => "J\xF6rg", 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null)),
		);

		$this->get(array('stage' => 'patch'));

		$this->assertSame(array(), $this->journal->starting('store'), 'MyISAM keeps what a failed batch stored');
	}

	public function testA12BoardsConfigurationForumsAndGroupsAreConvertedAtOnce(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['update:charset'] = 'ISO-8859-1';
		$this->settings->config['o_board_title'] = "Caf\xE9";
		$this->appliedUpTo('Update::convert_misc');
		$this->conversion->rows['categories'] = array(new TextRow(1, array('cat_name' => "Cat\xE9gorie")));
		$this->conversion->rows['forums'] = array(
			new TextRow(1, array('forum_name' => "F\xF6rum", 'forum_desc' => '', 'moderators' => serialize(array("J\xF6rg" => 3)))),
			new TextRow(2, array('forum_name' => 'Plain', 'forum_desc' => 'plain', 'moderators' => null)),
		);
		$this->conversion->rows['groups'] = array(new TextRow(4, array('g_title' => "Mod\xE9rateurs", 'g_user_title' => '')));

		$body = $this->get(array('stage' => 'patch'))->body;

		$this->assertStringStartsWith("Applying Update::convert_misc…<br />\nConverting configuration…<br />\nConverting categories…<br />\nConverting forums…<br />\nConverting groups…<br />\nConverting ranks…<br />\nConverting censor words…<br />", $body);
		$this->assertContains('set o_board_title=Café', $this->journal->entries);
		$this->assertSame(array(
			"store categories 1 cat_name='Catégorie'",
			"store forums 1 forum_name='Förum' forum_desc=NULL moderators='a:1:{s:5:\"Jörg\";i:3;}'",
			"store groups 4 g_title='Modérateurs' g_user_title=NULL",
		), $this->journal->starting('store'), 'the moderators are keyed by their converted names, and a plain forum is left alone');
		$this->assertSame(array('record Update::convert_misc'), $this->journal->starting('record'));
	}

	public function testAMiscConversionInterruptedPartWayStoresItsRemainingValuesOnly(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['update:charset'] = 'ISO-8859-1';
		$this->settings->config['o_board_title'] = '&amp;amp;';
		$this->settings->config['o_ext,a=b'] = '&amp;amp;';
		$this->appliedUpTo('Update::convert_misc');
		$this->conversion->rows['categories'] = array(new TextRow(1, array('cat_name' => '&amp;amp;')));
		$this->conversion->rows['groups'] = array(new TextRow(4, array('g_title' => "Mod\xE9rateurs", 'g_user_title' => '')));
		$this->conversion->storesLeft = 1;

		$this->assertConnectionLost();

		$this->journal->entries = array();
		$this->conversion->storesLeft = PHP_INT_MAX;
		$this->get(array('stage' => 'patch'));

		$this->assertSame('&amp;', $this->settings->config['o_board_title'], 'a stored option is not decoded twice');
		$this->assertNotContains('set o_board_title=&', $this->journal->entries);
		$this->assertSame('&amp;', $this->settings->config['o_ext,a=b'], 'an option whose name holds the journal\'s separators is not decoded twice');
		$this->assertSame(array("store groups 4 g_title='Modérateurs' g_user_title=NULL"), $this->journal->starting('store'), 'a stored row is not decoded twice');
	}

	public function testAMiscConversionJournalFitsATextColumnHoweverManyRowsItRecords(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['update:charset'] = 'ISO-8859-1';
		$this->appliedUpTo('Update::convert_misc');
		$this->conversion->rows['censoring'] = array_map(static fn (int $id): TextRow => new TextRow($id, array('search_for' => "w\xF6rd", 'replace_with' => '&amp;amp;')), range(1, 3000));
		$this->conversion->storesLeft = 2000;
		$this->settings->config['update:converted_misc_theme'] = 'dark';
		$this->settings->config['update:converted_misc_1'] = 'kept';
		$this->settings->config['update:converted_misc_99'] = 'kept';

		$this->assertConnectionLost();

		foreach ($this->settings->config as $name => $value)
			$this->assertLessThanOrEqual(65535, strlen($value ?? ''), $name.' fits a MySQL TEXT column');

		$this->journal->entries = array();
		$this->conversion->storesLeft = PHP_INT_MAX;
		$this->get(array('stage' => 'patch'));

		$stored = $this->journal->starting('store');
		$this->assertCount(1000, $stored, 'a stored row is not decoded twice');
		$this->assertSame("store censoring 2001 search_for='wörd' replace_with='&amp;'", $stored[0]);

		$this->journal->entries = array();
		$this->applied->names = self::PATCHES;
		$this->get(array('stage' => 'finish'));

		$this->assertSame(array(), array_filter(array_keys($this->settings->config), static fn (string $name): bool => preg_match('/^update:converted_misc(_\d+|_written)?$/', $name) === 1 && !in_array($name, array('update:converted_misc_1', 'update:converted_misc_99'), true)), 'the finish removes every part of the journal');
		$this->assertSame('dark', $this->settings->config['update:converted_misc_theme'], 'an extension\'s option sharing the prefix is kept');
		$this->assertSame('kept', $this->settings->config['update:converted_misc_1'], 'an extension\'s numbered option the journal would have used is kept');
		$this->assertSame('kept', $this->settings->config['update:converted_misc_99'], 'an extension\'s numbered option beyond the journal is kept');
	}

	public function testTheFinishKeepsAnExtensionsOUpdateOptions(): void {
		$this->settings->config['o_update_groups'] = 'ext';
		$this->settings->config['o_update_converted'] = 'ext';
		$this->applied->names = self::PATCHES;

		$this->get(array('stage' => 'finish'));

		$this->assertSame('ext', $this->settings->config['o_update_groups']);
		$this->assertSame('ext', $this->settings->config['o_update_converted']);
	}

	public function testTheFinishRemovesEveryJournalPartAnInterruptedWriteLeft(): void {
		$this->settings->config['update:converted_misc_written'] = 'update:converted_misc_1,update:converted_misc_3';
		$this->settings->config['update:converted_misc_1'] = 'a=b';
		$this->settings->config['update:converted_misc_3'] = 'c=d';
		$this->settings->config['update:converted_misc_4'] = 'kept';
		$this->applied->names = self::PATCHES;

		$this->get(array('stage' => 'finish'));

		$this->assertSame(array('remove setting update:converted_misc_1,update:converted_misc_3,update:charset,update:converted,update:converted_misc,update:converted_misc_written,update:groups,update:altering', 'remove setting update:under_way'), $this->journal->starting('remove setting'), 'the parts go before the lists naming them');
		$this->assertArrayNotHasKey('update:converted_misc_written', $this->settings->config);
		$this->assertArrayNotHasKey('update:converted_misc_1', $this->settings->config);
		$this->assertArrayNotHasKey('update:converted_misc_3', $this->settings->config, 'a part the interrupted write claimed is removed');
		$this->assertSame('kept', $this->settings->config['update:converted_misc_4'], 'an option the update never wrote is kept');
	}

	public function testABatchStartMeantForAnotherPatchIsIgnored(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['update:charset'] = 'ISO-8859-1';
		$this->appliedUpTo('Update::convert_users');
		$this->conversion->rows['users'] = array(new TextRow(2, array('username' => "J\xF6rg", 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null)));

		$this->assertStringContainsString('Converting user 2…', $this->get(array('stage' => 'patch', 'patch' => 'Update::convert_topics', 'start_at' => '400'))->body);
	}

	public function testABatchStartBeyondTheIntegerRangeIsKeptInsideIt(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['update:charset'] = 'ISO-8859-1';
		$this->appliedUpTo('Update::convert_users');

		$this->assertSame(array('stage' => 'patch'), self::next($this->get(array('stage' => 'patch', 'patch' => 'Update::convert_users', 'start_at' => '9223372036854775807'))->body));
	}

	public function testA14BoardConvertsNoText(): void {
		$this->settings->config['update:charset'] = 'ISO-8859-1';
		$this->conversion->rows['users'] = array(new TextRow(2, array('username' => "J\xF6rg", 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null)));

		$this->applyAll();

		$this->assertSame(array(), $this->journal->starting('store'));
	}

	public function testMysqlReadsA12BoardsTablesAsUtf8ThroughABinaryType(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->appliedUpTo('Update::convert_tables');

		$body = $this->get(array('stage' => 'patch'))->body;

		$this->assertStringStartsWith("Applying Update::convert_tables…<br />\nConverting table pun_bans…<br />\nConverting table pun_categories…<br />", $body);
		$this->assertSame(array('alter bans.title varbinary(50)', 'alter bans.title varchar(50) CHARACTER SET utf8'), array_slice($this->journal->starting('alter'), 0, 2));
		$this->assertContains('alter search_words.word varchar(20) CHARACTER SET utf8 COLLATE utf8_bin', $this->journal->entries, 'a binary collation stays binary');
		$this->assertSame(19, count($this->journal->starting('charset')));
	}

	public function testAColumnLeftBinaryByAnInterruptedRunIsFinished(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['update:altering'] = 'bans:title:0:varchar(50)';
		$this->appliedUpTo('Update::convert_tables');
		$this->conversion->columns['bans'] = array(new TableColumn('title', 'varbinary(50)', null, true, null));

		$this->get(array('stage' => 'patch'));

		$this->assertSame(array('alter bans.title varchar(50) CHARACTER SET utf8'), array_slice($this->journal->starting('alter'), 0, 1));
		$this->assertSame('set update:altering=categories:title:0:varchar(50)', array_values(array_filter($this->journal->entries, static fn (string $entry): bool => str_starts_with($entry, 'set update:altering=')))[0], 'the next column is recorded before it is altered');
	}

	public function testAColumnLeftBinaryWithABinaryCollationIsFinishedBinary(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['update:altering'] = 'search_words:word:1:varchar(20)';
		$this->appliedUpTo('Update::convert_tables');
		$this->conversion->columns['search_words'] = array(new TableColumn('word', 'varbinary(20)', null, false, ''));

		$this->get(array('stage' => 'patch'));

		$this->assertSame(array('alter search_words.word varchar(20) CHARACTER SET utf8 COLLATE utf8_bin'), $this->journal->starting('alter search_words'));
	}

	public function testAColumnARunRecordedButNeverAlteredTakesBothSteps(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['update:altering'] = 'bans:title:0:varchar(50)';
		$this->appliedUpTo('Update::convert_tables');

		$this->get(array('stage' => 'patch'));

		$this->assertSame(array('alter bans.title varbinary(50)', 'alter bans.title varchar(50) CHARACTER SET utf8'), $this->journal->starting('alter bans'));
	}

	public function testPostsAndSignaturesArePreparsed(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->appliedUpTo('Update::preparse_posts');
		$this->conversion->rows['posts'] = array(new TextRow(7, array('message' => '[B]Hi[/B]')), new TextRow(8, array('message' => null)));
		$this->conversion->rows['users'] = array(new TextRow(1, array('signature' => '[I]Sig[/I]')));

		$this->assertCount(2, $this->applyAll());
		$this->assertSame(array("store posts 7 message='[b]hi[/b]'", "store posts 8 message=''", "store users 1 signature='[i]sig[/i] (sig)'"), $this->journal->starting('store'));
	}

	public function testAPatchTheTextDefeatsStopsTheUpdateAtItWithItsBatchDiscarded(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['update:charset'] = 'NO-SUCH-SET';
		$this->appliedUpTo('Update::convert_users');
		$this->conversion->rows['users'] = array(new TextRow(2, array('username' => "J\xF6rg", 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null)));

		$this->assertSame('Data patch Update::convert_users failed: Failed to convert a value to UTF-8 from the requested character set. Conversion aborted. The patches before it are applied; the update goes on from this one when it is run again.', $this->get(array('stage' => 'patch'))->body);
		$this->assertSame(array('roll back', 'end transaction', 'close'), array_slice($this->journal->entries, -3));
		$this->assertSame(array(), $this->journal->starting('record'));
	}

	public function testAnyOtherFailureIsRaisedOnceTheBatchIsDiscarded(): void {
		$this->data->mailFails = true;
		$this->appliedUpTo('Update::group_mail');

		try {
			$this->get(array('stage' => 'patch'));
			$this->fail('the failure was swallowed');
		}
		catch (PatchException $e) {
			$this->assertSame('Data patch Update::group_mail failed', $e->getMessage());
			$this->assertSame('the groups table is gone', $e->getPrevious()?->getMessage());
		}

		$this->assertSame('roll back', array_slice($this->journal->entries, -1)[0]);
		$this->assertSame(array(), $this->journal->starting('record'));
		$this->assertSame(array(), $this->journal->starting('version'));
	}

	public function testABoardWithEveryPatchRecordedGoesOnToTheFinish(): void {
		$this->applied->names = self::PATCHES;

		$this->assertSame(array('stage' => 'finish'), self::next($this->get(array('stage' => 'patch'))->body));
		$this->assertSame(array(), $this->journal->starting('record'));
	}

	public function testAFinishRequestedWithAPatchPendingRecordsNoReleaseAndGoesOnToThePatches(): void {
		$this->appliedUpTo('Update::preparse_signatures');

		$body = $this->get(array('stage' => 'finish'))->body;

		$this->assertSame(array('stage' => 'patch'), self::next($body));
		$this->assertSame('1.4.4', $this->settings->config['o_cur_version']);
		$this->assertSame('4', $this->settings->config['o_database_revision']);
		$this->assertSame(array(), $this->journal->starting('set o_'));
		$this->assertSame(array(), $this->journal->starting('remove setting'));
		$this->assertSame(array(), $this->journal->starting('version'), 'no module\'s data is done');
	}

	public function testABoardFromBeforeModuleVersionsIsRecordedWholeAndARunAgainChangesNothing(): void {
		$this->get(array('stage' => 'start'));
		$this->applyAll();
		$this->get(array('stage' => 'finish'));

		$recorded = array();
		foreach ($this->declared as $module => $version)
			$recorded[$module] = new InstalledVersion($version, $version);

		$this->assertEquals($recorded, $this->versions->versions, 'every module at its version, schema and data');
		$this->assertLessThan(array_search('set o_cur_version=1.5.1', $this->journal->entries, true), array_search('version Framework data 2.0.0', $this->journal->entries, true), 'the release is recorded last');
		$this->assertStringContainsString('already as up-to-date', $this->get()->body);

		// Set back to its release, it goes from the start straight to the finish
		$this->settings->config['o_cur_version'] = '1.4.4';
		$this->settings->config['o_database_revision'] = '4';
		$this->journal->entries = array();

		$body = $this->get(array('stage' => 'start'))->body;
		$this->assertStringNotContainsString('…', $body);
		$this->assertSame(array('stage' => 'finish'), self::next($body));

		$this->get(array('stage' => 'finish'));
		$this->assertSame(array(), array_merge($this->journal->starting('create'), $this->journal->starting('record'), $this->journal->starting('version')));
		$this->assertEquals($recorded, $this->versions->versions);
	}

	public function testTheFinishRecordsTheReleaseAndMovesTheAddressIntoConfigPhp(): void {
		$this->applied->names = self::PATCHES;
		$body = $this->get(array('stage' => 'finish'))->body;

		$this->assertSame(array('sync 1', 'sync 2', 'empty search cache', 'empty online', 'clear cache', 'set o_cur_version=1.5.1', 'set o_database_revision=6'), array_values(array_filter($this->journal->entries, static fn (string $entry): bool => preg_match('/^(set o_(cur|database)|sync|empty|clear)/', $entry) === 1)));
		$this->assertSame(array('remove setting update:charset,update:converted,update:converted_misc,update:converted_misc_written,update:groups,update:altering', 'remove setting update:under_way'), $this->journal->starting('remove setting'));
		$this->assertStringContainsString('<h1 class="hn"><span>PunBB Database Update completed!</span></h1>', $body);
		$this->assertStringContainsString('You may <a href="http://forum.test/index.php">go to the forum index</a> now.', $body);
		$this->assertSame(array(), $this->journal->starting('replace config'));

		$this->journal->entries = array();
		$this->settings->config['o_cur_version'] = '1.4.4';
		$this->settings->config['o_base_url'] = 'http://old.test';
		$this->configuration->configuration = new BoardConfiguration(new DatabaseSettings('mysqli', 'db', 'forum', 'user', 'secret', 'pun_', true), null, 'cookie', '.forum.test', '/forum/', true);
		$this->files->writable = false;

		$body = $this->get(array('stage' => 'finish'))->body;

		$this->assertSame(array('remove setting update:charset,update:converted,update:converted_misc,update:converted_misc_written,update:groups,update:altering', 'remove setting update:under_way'), $this->journal->starting('remove setting'));
		$this->assertSame('http://old.test', $this->settings->config['o_base_url'], 'until the copy is saved, the stored address is what the forum and a rerun fall back to');
		$this->assertStringContainsString(htmlspecialchars("\$p_connect = true;\n\n\$base_url = 'http://old.test';\n\n\$cookie_name = 'cookie';\n\$cookie_domain = '.forum.test';\n\$cookie_path = '/forum/';\n\$cookie_secure = 1;\n\ndefine('FORUM', 1);", ENT_QUOTES).'</textarea>', $body);
		$this->assertStringNotContainsString('FORUM_DEBUG', $body, 'an updated config.php offers no options');

		$this->journal->entries = array();
		$this->settings->config['o_cur_version'] = '1.4.4';
		$this->files->writable = true;

		$this->get(array('stage' => 'finish'));

		$this->assertStringContainsString("\$base_url = 'http://old.test';", $this->files->written ?? '');
		$this->assertSame(array('remove setting o_base_url', 'set o_cur_version=1.5.1', 'set o_database_revision=6'), array_values(array_filter($this->journal->entries, static fn (string $entry): bool => in_array($entry, array('remove setting o_base_url', 'set o_cur_version=1.5.1', 'set o_database_revision=6'), true))), 'the release is recorded after the address moves, so an interrupted finish is run again');
		$this->assertArrayNotHasKey('o_base_url', $this->settings->config);
	}
}
