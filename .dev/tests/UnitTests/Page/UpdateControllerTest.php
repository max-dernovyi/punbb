<?php
/**
 * admin/db_update.php as a module, built with no forum: what it answers before
 * it opens the database, the checks that a board is one it updates, the start
 * form, the structure a 1.4 board is brought to, a 1.2 board's conversion
 * stages and the finish.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Setup\Config\BoardConfiguration;
use PunBB\Module\Setup\Database\DatabaseSettings;
use PunBB\Module\Setup\Page\SetupPage;
use PunBB\Module\Update\Api\BoardDataInterface;
use PunBB\Module\Update\Api\BoardSettingsInterface;
use PunBB\Module\Update\Api\ConversionInterface;
use PunBB\Module\Update\Api\Data\PostRangeInterface;
use PunBB\Module\Update\Api\Data\SettingInterface;
use PunBB\Module\Update\Api\Data\TextRowInterface;
use PunBB\Module\Update\Controller\Stages;
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
			$this->journal->add('add setting '.$setting->name().'='.$setting->value());
	}

	public function update(SettingInterface ...$settings): void {
		foreach ($settings as $setting)
			$this->journal->add('set '.$setting->name().'='.$setting->value());
	}

	public function replace(SettingInterface $setting, string $expected): void { $this->journal->add('replace '.$setting->name().' '.$expected.'='.$setting->value()); }

	public function rename(string $from, string $to): void { $this->journal->add('rename '.$from.' '.$to); }

	public function remove(string ...$names): void { $this->journal->add('remove setting '.implode(',', $names)); }
}

final class FakeBoardData implements BoardDataInterface {
	/** @var list<string> */
	public array $samples = array('Hello', 'Ünïcode');

	public function __construct(private readonly SetupJournal $journal) {}

	public function reorderGroups(): void { $this->journal->add('reorder groups'); }

	public function grantModerators(string $permission, int $value): void { $this->journal->add('grant '.$permission.'='.$value); }

	public function limitGroupMail(): void { $this->journal->add('limit group mail'); }

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

		$this->journal->add('store '.$table.' '.$row->id().' '.implode(' ', $values));
	}

	public function columns(string $table): array {
		return $table === 'search_words' ? array(new TableColumn('word', 'varchar(20)', 'latin1_swedish_ci', false, '')) : array(new TableColumn('title', 'varchar(50)', 'latin1_swedish_ci', true, null), new TableColumn('id', 'int(10) unsigned', null, false, null));
	}

	public function setDefaultCharset(string $table): void { $this->journal->add('charset '.$table); }
}

final class FakePreparser implements PreparserInterface {
	public function preparse(string $text, bool $signature): string { return strtolower($text).($signature ? ' (sig)' : ''); }
}

class UpdateControllerTest extends TestCase {
	private SetupJournal $journal;

	private FakeEnvironment $environment;

	private FakeSetupConfiguration $configuration;

	private FakeBoardFiles $files;

	private FakeSchema $schema;

	private FakeBoardSettings $settings;

	private FakeBoardData $data;

	private FakeConversion $conversion;

	private UpdateController $controller;

	protected function setUp(): void {
		$this->journal = new SetupJournal();
		$this->environment = new FakeEnvironment();
		$this->configuration = new FakeSetupConfiguration();
		$this->files = new FakeBoardFiles($this->journal);
		$this->schema = new FakeSchema($this->journal);
		$this->schema->tables = array('config', 'search_cache', 'extensions', 'extension_hooks', 'forum_subscriptions');
		$this->settings = new FakeBoardSettings($this->journal);
		$this->data = new FakeBoardData($this->journal);
		$this->conversion = new FakeConversion($this->journal);
		$database = new FakeSetupDatabase($this->journal);
		$pages = new SetupPage(new TemplateRenderer());

		$this->controller = new UpdateController($this->environment, $this->configuration, $database, $pages,
			fn (): Update => new Update($this->settings, $this->data, $this->schema, $database, $this->environment, $this->files, $pages, new TemplateRenderer(),
				new Stages($this->settings, $this->data, $this->conversion, $this->schema, $database, $this->environment, $this->files, new FakePreparser())));
	}

	/** @param array<string, string> $query */
	private function get(array $query = array()): Response {
		return $this->controller->handle(new Request('GET', '/', 'admin/db_update.php', $query));
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
		$this->settings->config['o_cur_version'] = '1.5.1';
		$this->settings->config['o_database_revision'] = '6';

		$response = $this->get();

		$this->assertSame(503, $response->status);
		$this->assertStringContainsString('<title>Error - Board &amp; co</title>', $response->body);
		$this->assertStringContainsString("\t<h1>Sorry! The page could not be loaded.</h1>\n<p>Your database is already as up-to-date as this script can make it.</p>\n</body>\n</html>\n", $response->body);
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

	public function testTheStartBringsA14BoardsStructureUpAndGoesOnToTheFinish(): void {
		$this->files->avatarFiles = array('3.png' => array(60, 60), '4.jpg' => array(100, 60), '1.gif' => array(1, 1), 'x.png' => array(1, 1), '5.gif' => null);
		$this->settings->config += array('o_avatars_width' => '60', 'o_avatars_height' => '60');

		$body = $this->get(array('stage' => 'start'))->body;

		$this->assertStringContainsString('<script type="text/javascript">window.location="db_update.php?stage=finish"</script><br />JavaScript seems to be disabled. <a href="db_update.php?stage=finish">Click here to continue</a>.', $body);
		$this->assertSame(array(), $this->journal->starting('create'), 'the tables it finds are not created again');
		$this->assertContains('add users.avatar_width TINYINT(3) UNSIGNED after avatar', $this->journal->entries);
		$this->assertContains('alter users.password VARCHAR(255)', $this->journal->entries);
		$this->assertContains('index online.user_id_ident_idx user_id,ident(25) unique', $this->journal->entries);
		$this->assertContains('drop index topics.subject_idx', $this->journal->entries);
		$this->assertSame(array('avatar 3 3 60x60'), $this->journal->starting('avatar'));
		$this->assertSame(array('remove avatar 4.jpg', 'remove avatar 5.gif'), $this->journal->starting('remove avatar'));
		$this->assertContains('set o_timeout_visit=1800', $this->journal->entries);
		$this->assertContains('rename o_server_timezone o_default_timezone', $this->journal->entries);
		$this->assertContains('add setting o_sef=Default', $this->journal->entries);
		$this->assertContains('reorder groups', $this->journal->entries);
		$this->assertContains('replace o_default_user_group 4=3', $this->journal->entries);
		$this->assertContains('remove extension hotfix_1_4_3', $this->journal->entries);
		$this->assertNotContains('linkedin', $this->journal->entries, 'only a board between 1.3 and 1.4.1 stored them');
		$this->assertSame(array('end transaction', 'close'), array_slice($this->journal->entries, -2));
	}

	public function testA12BoardsOptionsForEveryModeratorBecomeGroupPermissions(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->settings->config['p_mod_rename_users'] = '1';
		$this->schema->fields = array('groups.g_moderator');

		$body = $this->get(array('stage' => 'start', 'convert_charset' => '1', 'req_old_charset' => 'iso8859-15'))->body;

		$this->assertStringContainsString('window.location="db_update.php?stage=conv_misc\u0026req_old_charset=ISO-8859-15\u0026req_per_page=300"', $body);
		$this->assertStringContainsString('<a href="db_update.php?stage=conv_misc&amp;req_old_charset=ISO-8859-15&amp;req_per_page=300">', $body);
		$this->assertSame(array('remove setting p_mod_rename_users'), $this->journal->starting('remove setting'));
		$this->assertContains('add groups.g_mod_rename_users TINYINT(1) after g_mod_edit_users', $this->journal->entries);
		$this->assertContains('grant g_mod_rename_users=1', $this->journal->entries);
		$this->assertNotContains('reorder groups', $this->journal->entries);

		$this->assertStringContainsString('window.location="db_update.php?stage=conv_tables"', $this->get(array('stage' => 'start'))->body, 'without the conversion the tables are next');
	}

	public function testAnUnknownCharacterSetIsRefused(): void {
		$this->assertSame('Unknown character set. Set req_old_charset to an encoding this PHP installation supports.', $this->get(array('stage' => 'conv_misc', 'req_old_charset' => 'NO-SUCH-SET'))->body);
	}

	public function testAConversionStageConvertsABatchAndGoesOnToTheNext(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';
		$this->conversion->rows['users'] = array(
			new TextRow(2, array('username' => "J\xF6rg", 'title' => '', 'realname' => null, 'location' => "K&ouml;ln", 'signature' => '&#8364;', 'admin_note' => null)),
			new TextRow(3, array('username' => 'plain', 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null)),
			new TextRow(400, array('username' => 'later', 'title' => null, 'realname' => null, 'location' => null, 'signature' => null, 'admin_note' => null)),
		);

		$body = $this->get(array('stage' => 'conv_users', 'req_old_charset' => 'ISO-8859-1'))->body;

		$this->assertStringStartsWith("Converting user 2…<br />\nConverting user 3…<br />\n<script", $body);
		$this->assertSame(array("store users 2 username='Jörg' title=NULL realname=NULL location='Köln' signature='€' admin_note=NULL"), $this->journal->starting('store'));
		$this->assertStringContainsString('window.location="db_update.php?stage=conv_users\u0026req_old_charset=ISO-8859-1\u0026req_per_page=300\u0026start_at=400"', $body);

		$this->assertStringContainsString('window.location="db_update.php?stage=conv_topics', $this->get(array('stage' => 'conv_users', 'req_old_charset' => 'ISO-8859-1', 'start_at' => '302'))->body);
		$this->assertStringContainsString('window.location="db_update.php?stage=conv_tables"', $this->get(array('stage' => 'conv_posts', 'req_old_charset' => 'ISO-8859-1'))->body);
	}

	public function testABatchStartBeyondTheIntegerRangeIsKeptInsideIt(): void {
		$this->settings->config['o_cur_version'] = '1.2.15';

		$this->assertStringContainsString('window.location="db_update.php?stage=conv_topics', $this->get(array('stage' => 'conv_users', 'req_old_charset' => 'ISO-8859-1', 'start_at' => '9223372036854775807'))->body);
	}

	public function testA14BoardSkipsTheConversionStages(): void {
		$this->assertStringContainsString('window.location="db_update.php?stage=conv_tables"', $this->get(array('stage' => 'conv_users'))->body);
		$this->assertSame(array(), $this->journal->starting('store'));
	}

	public function testMysqlConvertsItsTablesThroughABinaryType(): void {
		$body = $this->get(array('stage' => 'conv_tables'))->body;

		$this->assertStringStartsWith("Converting table pun_bans…<br />\nConverting table pun_categories…<br />", $body);
		$this->assertStringContainsString('window.location="db_update.php?stage=preparse_posts"', $body);
		$this->assertSame(array('alter bans.title varbinary(50)', 'alter bans.title varchar(50) CHARACTER SET utf8'), array_slice($this->journal->starting('alter'), 0, 2));
		$this->assertSame(19, count($this->journal->starting('charset')));
	}

	public function testPostsAndSignaturesArePreparsed(): void {
		$this->conversion->rows['posts'] = array(new TextRow(7, array('message' => '[B]Hi[/B]')), new TextRow(8, array('message' => null)));
		$this->conversion->rows['users'] = array(new TextRow(1, array('signature' => '[I]Sig[/I]')));

		$this->assertStringContainsString('window.location="db_update.php?stage=preparse_sigs"', $this->get(array('stage' => 'preparse_posts'))->body);
		$this->assertStringContainsString('window.location="db_update.php?stage=finish"', $this->get(array('stage' => 'preparse_sigs'))->body);
		$this->assertSame(array("store posts 7 message='[b]hi[/b]'", "store posts 8 message=''", "store users 1 signature='[i]sig[/i] (sig)'"), $this->journal->starting('store'));
	}

	public function testTheFinishRecordsTheReleaseAndMovesTheAddressIntoConfigPhp(): void {
		$body = $this->get(array('stage' => 'finish'))->body;

		$this->assertSame(array('set o_cur_version=1.5.1', 'set o_database_revision=6', 'sync 1', 'sync 2', 'empty search cache', 'empty online', 'clear cache'), array_values(array_filter($this->journal->entries, static fn (string $entry): bool => preg_match('/^(set o_(cur|database)|sync|empty|clear)/', $entry) === 1)));
		$this->assertStringContainsString('<h1 class="hn"><span>PunBB Database Update completed!</span></h1>', $body);
		$this->assertStringContainsString('You may <a href="http://forum.test/index.php">go to the forum index</a> now.', $body);
		$this->assertSame(array(), $this->journal->starting('replace config'));

		$this->settings->config['o_base_url'] = 'http://old.test';
		$this->configuration->configuration = new BoardConfiguration(new DatabaseSettings('mysqli', 'db', 'forum', 'user', 'secret', 'pun_', true), null, 'cookie', '.forum.test', '/forum/', true);
		$this->files->writable = false;

		$body = $this->get(array('stage' => 'finish'))->body;

		$this->assertSame(array('remove setting o_base_url'), $this->journal->starting('remove setting'));
		$this->assertStringContainsString(htmlspecialchars("\$p_connect = true;\n\n\$base_url = 'http://old.test';\n\n\$cookie_name = 'cookie';\n\$cookie_domain = '.forum.test';\n\$cookie_path = '/forum/';\n\$cookie_secure = 1;\n\ndefine('FORUM', 1);", ENT_QUOTES).'</textarea>', $body);
		$this->assertStringNotContainsString('FORUM_DEBUG', $body, 'an updated config.php offers no options');
	}
}
