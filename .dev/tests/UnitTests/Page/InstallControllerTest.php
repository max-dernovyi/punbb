<?php
/**
 * admin/install.php as a module, built with no forum: what it answers where a
 * board is installed or PHP cannot run one, the form, config.php to download,
 * each refusal of what was posted, and an installation: the tables, the rows
 * it stores in one transaction, config.php and the last page.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\DeclaredPatches;
use PunBB\Module\Database\Patch\PatchApplier;
use PunBB\Module\Database\Patch\PatchDeclaration;
use PunBB\Module\Database\Patch\PatchOwnerInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Database\Schema\DeclaredSchema;
use PunBB\Module\Database\Schema\SchemaSynchronizer;
use PunBB\Module\Database\Version\InstalledVersion;
use PunBB\Module\Database\Version\ModuleVersions;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\ModuleTree;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Install\Api\BoardInstallationInterface;
use PunBB\Module\Install\Api\Data\AdministratorInterface;
use PunBB\Module\Install\Api\Data\ExtensionInterface;
use PunBB\Module\Install\Api\Data\RankInterface;
use PunBB\Module\Install\Api\Data\SettingInterface;
use PunBB\Module\Install\Api\Data\WelcomeInterface;
use PunBB\Module\Install\Controller\InstallController;
use PunBB\Module\Install\Controller\Installation;
use PunBB\Module\Install\Indexing\PostIndexInterface;
use PunBB\Module\Install\Language\InstallerLanguageInterface;
use PunBB\Module\Install\Manifest\BundledExtensionsInterface;
use PunBB\Module\Install\Model\BundledExtension;
use PunBB\Module\Install\Model\BundledHook;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Setup\Page\SetupPage;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Security\PasswordsInterface;
use PunBB\Module\Site\Security\RandomKeysInterface;

require_once __DIR__.'/SetupFakes.php';

/** A third-party module with one data patch of two batches, each journalled. */
final class SeedingModule implements ModuleInterface, PatchOwnerInterface {
	public function __construct(private readonly SetupJournal $journal) {}

	public function name(): string { return 'Seeding'; }

	public function dependencies(): array { return array(); }

	public function loadAfter(): array { return array(); }

	public function version(): string { return '1.0.0'; }

	public function wire(Wiring $wiring): void {}

	public function patches(): array {
		$journal = $this->journal;

		return array(new PatchDeclaration('Seeding::seed', array(), static fn (): DataPatchInterface => new class ($journal) implements DataPatchInterface {
			public function __construct(private readonly SetupJournal $journal) {}

			public function apply(int $startAt): PatchStep {
				$this->journal->add('seed from '.$startAt);

				return new PatchStep(array(), $startAt === 0 ? 1 : null);
			}
		}));
	}
}

final class FakeBoardInstallation implements BoardInstallationInterface {
	public bool $installed = false;

	/** @var array<string, ?string> */
	public array $settings = array();

	public ?AdministratorInterface $administrator = null;

	public ?WelcomeInterface $welcome = null;

	public function __construct(private readonly SetupJournal $journal) {}

	public function isInstalled(): bool { return $this->installed; }

	public function addGroups(): void { $this->journal->add('groups'); }

	public function addGuest(): void { $this->journal->add('guest'); }

	public function addAdministrator(AdministratorInterface $administrator): int {
		$this->journal->add('administrator '.$administrator->username());
		$this->administrator = $administrator;

		return 2;
	}

	public function addSettings(SettingInterface ...$settings): void {
		$this->journal->add('settings');
		foreach ($settings as $setting)
			$this->settings[$setting->name()] = $setting->value();
	}

	public function addWelcome(WelcomeInterface $welcome): int {
		$this->journal->add('welcome by '.$welcome->posterId());
		$this->welcome = $welcome;

		return 1;
	}

	public function addRanks(RankInterface ...$ranks): void {
		$this->journal->add('ranks '.implode(', ', array_map(static fn (RankInterface $rank): string => $rank->title().' '.$rank->minPosts(), $ranks)));
	}

	public function addExtension(ExtensionInterface $extension): void {
		$this->journal->add('extension '.$extension->id().' with '.count($extension->hooks()).' hooks');
	}
}

final class FakeInstallerLanguage implements InstallerLanguageInterface {
	/** @var list<string> */
	public array $packs = array('English');

	public function packs(): array { return $this->packs; }

	public function speaksInstaller(string $language): bool { return in_array($language, $this->packs, true); }

	public function strings(string $language, string $file): array {
		$lang_install = $lang_admin_settings = array();
		require FORUM_ROOT.'lang/English/'.$file.'.php';

		return array_map(static fn (string $string): Html => new Html($string), $file === 'install' ? $lang_install : $lang_admin_settings);
	}
}

final class FakeBundledExtensions implements BundledExtensionsInterface {
	public bool $shipped = false;

	public function hasRepository(): bool { return $this->shipped; }

	public function repository(int $installed): ?ExtensionInterface {
		return new BundledExtension('pun_repository', 'Repository', '1.0', '', 'PunBB', array(new BundledHook('hd_head', '', 5, $installed)));
	}
}

final class FakeInstallerServices implements EmailAddressesInterface, RandomKeysInterface, PasswordsInterface, PostIndexInterface {
	public function __construct(private readonly SetupJournal $journal) {}

	public function isValid(string $address): bool { return str_contains($address, '@'); }

	public function isBanned(string $address): bool { return false; }

	public function key(int $length, bool $readable = false, bool $hash = false): string { return str_repeat($hash ? 'a' : 'k', $length); }

	public function hash(string $password): string { return 'hashed:'.$password; }

	public function verify(string $password, string $hash, string $salt): bool { return false; }

	public function verifyVisitor(string $password): bool { return false; }

	public function verifyAgainstNobody(string $password): void {}

	public function needsRehash(string $hash): bool { return false; }

	public function resetKeyLifetime(): int { return 3600; }

	public function index(int $postId, string $message, string $subject): void { $this->journal->add('index '.$postId.' '.$subject); }
}

class InstallControllerTest extends TestCase {
	private SetupJournal $journal;

	private FakeEnvironment $environment;

	private FakeBoardFiles $files;

	private FakeSetupDatabase $database;

	private FakeSchema $schema;

	private FakeBoardInstallation $board;

	private FakeInstallerLanguage $language;

	private FakeBundledExtensions $extensions;

	private ModuleVersions $moduleVersions;

	private InstallController $controller;

	protected function setUp(): void {
		$this->journal = new SetupJournal();
		$this->environment = new FakeEnvironment();
		$this->files = new FakeBoardFiles($this->journal);
		$this->database = new FakeSetupDatabase($this->journal);
		$this->schema = new FakeSchema($this->journal);
		$this->board = new FakeBoardInstallation($this->journal);
		$this->language = new FakeInstallerLanguage();
		$this->extensions = new FakeBundledExtensions();
		$this->controller = $this->installer();
	}

	/** The installer of the forum's own modules and each of $thirdParty found in modules/. */
	private function installer(ModuleInterface ...$thirdParty): InstallController {
		$services = new FakeInstallerServices($this->journal);
		$registry = ModuleRegistry::withThirdParty(ModuleRegistry::discover(ModuleTree::core(FORUM_ROOT))->modules(), array_values($thirdParty));
		$modules = $registry->modules();
		$patches = new PatchApplier(new DeclaredPatches(...$modules), new JournalAppliedPatches($this->journal), new Container(array()));
		$this->moduleVersions = new ModuleVersions(new JournalInstalledVersions($this->journal), ...$modules);

		return new InstallController($this->environment, $this->files, $this->database, $this->language, $this->extensions, $services, $services, new SetupPage(new TemplateRenderer()), new TemplateRenderer(),
			fn (): Installation => new Installation($this->board, $this->schema, new SchemaSynchronizer(new DeclaredSchema(...$modules), $this->schema), $patches, $this->moduleVersions, $this->database, $this->environment, $services, $services, $services, $this->extensions, $registry->thirdParty()));
	}

	/** @param array<string, mixed> $post */
	private function post(array $post): Response {
		return $this->controller->handle(new Request('POST', '/', 'admin/install.php', array(), $post, host: 'forum.test'));
	}

	/** @return array<string, string> a form that installs */
	private static function form(array $changes = array()): array {
		return $changes + array(
			'form_sent'		=> '1',
			'req_db_type'	=> 'sqlite3',
			'req_db_host'	=> '',
			'req_db_name'	=> 'forum.sqlite',
			'db_username'	=> '',
			'db_password'	=> '',
			'db_prefix'		=> '',
			'req_username'	=> ' admin ',
			'req_email'		=> 'Admin@Example.com',
			'req_password1'	=> 'secret',
			'req_language'	=> 'English',
			'req_base_url'	=> 'https://forum.test/',
		);
	}

	public function testAnInstalledBoardIsSentToItsIndex(): void {
		$this->files->config = true;

		$response = $this->controller->handle(new Request('GET', '/', 'admin/install.php'));

		$this->assertSame('The file \'config.php\' already exists which would mean that PunBB is already installed. You should go <a href="../index.php">here</a> instead.', $response->body);
		$this->assertSame(array(200, array()), array($response->status, $response->headers));
	}

	public function testAPhpInstallationLackingARequirementIsToldWhat(): void {
		$this->environment->errors = array('The following required PHP extensions are not loaded: intl.', 'No <database>.');

		$response = $this->controller->handle(new Request('GET', '/', 'admin/install.php'));

		$this->assertSame("PunBB cannot be installed on this PHP installation:\n<ul><li>The following required PHP extensions are not loaded: intl.</li><li>No &lt;database&gt;.</li></ul>", $response->body);
	}

	public function testALanguageWithoutTheInstallersStringsIsRefused(): void {
		$this->assertStringStartsWith('The language pack you have chosen doesn\'t seem to exist', $this->controller->handle(new Request('GET', '/', 'admin/install.php', array('lang' => 'Klingon')))->body);

		// A name is stripped of what would take its path out of lang/
		$this->assertStringContainsString('<title>PunBB Installation</title>', $this->controller->handle(new Request('GET', '/', 'admin/install.php', array('lang' => '../English')))->body);

		// The board's default language posted with the form is the installer's too
		$this->assertStringStartsWith('The language pack you have chosen doesn\'t seem to exist', $this->post(self::form(array('req_language' => 'Klingon')))->body);
		$this->assertSame(array(), $this->journal->entries);
	}

	public function testTheFormOffersTheDatabasesAndGuessesTheAddress(): void {
		$this->environment->types = array('mysqli', 'sqlite3');

		$response = $this->controller->handle(new Request('GET', '/forum/', 'admin/install.php', host: 'example.com:80', secure: false));

		$this->assertSame(array('Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store'), $response->headers);
		$this->assertStringContainsString('<strong>Install PunBB 1.5.1</strong>', $response->body);
		$this->assertStringContainsString('<option value="mysqli">MySQL Improved</option>', $response->body);
		$this->assertStringContainsString('<option value="sqlite3">SQLite3</option>', $response->body);
		$this->assertStringNotContainsString('PostgreSQL</option>', $response->body);
		$this->assertStringNotContainsString('uses InnoDB', $response->body, 'the storage engines are explained only when both MySQL drivers are offered');
		$this->assertStringContainsString('name="req_base_url" value="http://example.com/forum"', $response->body);
		$this->assertStringContainsString('<input type="hidden" name="req_language" value="English" />', $response->body);
		$this->assertStringNotContainsString('name="lang"', $response->body, 'one pack offers no choice');
		$this->assertStringNotContainsString('install_pun_repository', $response->body);
	}

	public function testTheFormOffersEachPackAndTheRepositoryTheForumShips(): void {
		$this->language->packs = array('English', 'Dutch');
		$this->extensions->shipped = true;

		$body = $this->controller->handle(new Request('GET', '/', 'admin/install.php', array('lang' => 'English')))->body;

		$this->assertSame(2, substr_count($body, '<option value="English" selected="selected">English</option>'));
		$this->assertSame(2, substr_count($body, '<option value="Dutch">Dutch</option>'));
		$this->assertStringContainsString('name="install_pun_repository" value="1" checked="checked"', $body);
	}

	public function testConfigPhpIsDownloadedAsTheLastPagePostsIt(): void {
		$response = $this->post(array('generate_config' => '1', 'db_type' => 'pgsql', 'db_host' => 'db', 'db_name' => 'forum', 'db_username' => 'u', 'db_password' => "it's", 'db_prefix' => 'pun_', 'base_url' => 'https://forum.test', 'cookie_name' => 'forum_cookie_x'));

		$this->assertSame(array('Content-Type' => 'text/x-delimtext; name="config.php"', 'Content-disposition' => 'attachment; filename=config.php'), $response->headers);
		$this->assertStringStartsWith("<?php\n\n\$db_type = 'pgsql';\n\$db_host = 'db';", $response->body);
		$this->assertStringContainsString("\$db_password = 'it\\'s';", $response->body);
		$this->assertStringContainsString("\$cookie_name = 'forum_cookie_x';\n\$cookie_domain = '';\n\$cookie_path = '/';\n\$cookie_secure = 1;", $response->body);

		$this->assertSame('Bad request. Every configuration field must be a single value.', $this->post(array('generate_config' => '1', 'db_host' => array('x')))->body);
	}

	/** @return array<string, array{array<string, mixed>, string}> */
	public static function refusalProvider(): array {
		return array(
			'no database name'		=> array(array('req_db_name' => ' '), 'You must enter a database name.'),
			'a short username'		=> array(array('req_username' => 'a'), 'Usernames must be at least 2 characters long.'),
			'a long username'		=> array(array('req_username' => str_repeat('é', 26)), 'Usernames must be no more than 25 characters long.'),
			'a short password'		=> array(array('req_password1' => 'abc'), 'Passwords must be at least 4 characters long.'),
			'guest'					=> array(array('req_username' => 'Guest'), 'The username guest is reserved.'),
			'an address'			=> array(array('req_username' => '192.0.2.1'), 'Usernames may not be in the form of an IP address.'),
			'reserved characters'	=> array(array('req_username' => '[a]\'"'), 'Usernames may not contain all the characters'),
			'BBCode'				=> array(array('req_username' => 'a[b]c'), 'Usernames may not contain any of the text formatting tags'),
			'an invalid email'		=> array(array('req_email' => 'nobody'), 'The administrator email address you entered is invalid.'),
			'no address'			=> array(array('req_base_url' => '/'), 'You must enter a base URL.'),
			'an unknown database'	=> array(array('req_db_type' => '<oracle>'), '\'&lt;oracle&gt;\' is not a valid database type.'),
			'an illegal prefix'		=> array(array('db_prefix' => '1<b>'), 'The table prefix \'1&lt;b&gt;\' contains illegal characters'),
			'SQLite\'s prefix'		=> array(array('db_prefix' => 'SQLite_'), 'The table prefix \'sqlite_\' is reserved'),
		);
	}

	/** @param array<string, mixed> $changes */
	#[\PHPUnit\Framework\Attributes\DataProvider('refusalProvider')]
	public function testWhatThePostedFormIsRefusedForIsTheErrorPage(array $changes, string $message): void {
		$response = $this->post(self::form($changes));

		$this->assertSame(503, $response->status);
		$this->assertStringContainsString('<title>Error - PunBB</title>', $response->body);
		$this->assertStringContainsString('<p>'.$message, $response->body);
		$this->assertSame(array(), $this->journal->starting('create'), 'nothing was installed');
	}

	public function testAMysqlServerTooOldOrWithoutInnodbIsRefused(): void {
		$this->database->version = '4.0.27';
		$this->assertStringContainsString('You are running MySQL version 4.0.27. PunBB requires at least MySQL 4.1.2', $this->post(self::form(array('req_db_type' => 'mysqli')))->body);

		$this->database->version = '8.4.0';
		$this->database->innodb = false;
		$this->assertStringContainsString('You are running MySQL version without InnoDB support.', $this->post(self::form(array('req_db_type' => 'mysqli_innodb')))->body);
		$this->assertSame(array(), $this->journal->starting('create'));
	}

	public function testABoardInstalledInTheDatabaseIsNamed(): void {
		$this->schema->tables = array('users');
		$this->board->installed = true;

		$response = $this->post(self::form(array('db_prefix' => 'pun_')));

		$this->assertStringContainsString('A table called "pun_users" is already present in the database "forum.sqlite".', $response->body);
		$this->assertSame(array(), $this->journal->starting('create'));
	}

	public function testAnInstallationStoresTheBoardInOneTransactionAndWritesConfigPhp(): void {
		$response = $this->post(self::form());

		$versions = array();
		foreach ($this->moduleVersions->declared() as $module => $version)
			array_push($versions, 'version '.$module.' schema '.$version, 'version '.$module.' data '.$version);

		$this->assertSame(array(
			'open sqlite3 forum.sqlite ',
			'start transaction',
			'create data_patches', 'create modules', 'create online', 'create users', 'create bans', 'create categories', 'create censoring', 'create extensions', 'create extension_hooks', 'create forum_perms', 'create forums', 'create groups', 'create subscriptions', 'create forum_subscriptions', 'create posts', 'create topics', 'create ranks', 'create reports', 'create search_cache', 'create search_matches', 'create search_words', 'create config',
			'record Update::avatars', 'record Update::options', 'record Update::moderator_groups', 'record Update::group_mail', 'record Update::first_posts', 'record Update::unverified_users', 'record Update::linkedin_addresses',
			'record Update::convert_misc', 'record Update::convert_reports', 'record Update::convert_search_words', 'record Update::convert_users', 'record Update::convert_topics', 'record Update::convert_posts', 'record Update::convert_tables', 'record Update::preparse_posts', 'record Update::preparse_signatures',
			...$versions,
			'groups', 'guest', 'administrator admin', 'settings', 'welcome by 2', 'index 1 Test post', 'ranks New member 0, Member 10',
			'end transaction',
			'clear cache',
			'write config',
			'close',
		), $this->journal->entries);

		$this->assertSame(array('admin', 'hashed:secret', 'kkkkkkkkkkkk', 'admin@example.com', 'English'), array($this->board->administrator?->username(), $this->board->administrator?->passwordHash(), $this->board->administrator?->salt(), $this->board->administrator?->email(), $this->board->administrator?->language()));
		$this->assertSame(array('1.5.1', '6', 'English', 'admin@example.com', '1', '0', null, 'Sample announcement'), array($this->board->settings['o_cur_version'], $this->board->settings['o_database_revision'], $this->board->settings['o_default_lang'], $this->board->settings['o_admin_email'], $this->board->settings['o_avatars'], $this->board->settings['o_check_for_updates'], $this->board->settings['o_smtp_host'], $this->board->settings['o_announcement_heading']));
		$this->assertSame(array('Test category', 'Test forum', 'Test post', 'admin'), array($this->board->welcome?->category(), $this->board->welcome?->forum(), $this->board->welcome?->subject(), $this->board->welcome?->poster()));

		$this->assertSame(array(), $this->moduleVersions->behind(), 'every module is recorded at its declared version, schema and data');
		$this->assertEquals(new InstalledVersion('1.5.0', '1.5.0'), $this->moduleVersions->installed('Site'));

		$this->assertStringContainsString("\$base_url = 'https://forum.test';\n\n\$cookie_name = 'forum_cookie_aaaaaa';", (string) $this->files->written);
		$this->assertStringContainsString("\$cookie_secure = 1;", (string) $this->files->written);

		$this->assertSame(200, $response->status);
		$this->assertStringContainsString('Final instructions', $response->body);
		$this->assertStringContainsString('PunBB has been fully installed! You may now <a href="../index.php">go to the forum index</a>.', $response->body);
		$this->assertStringNotContainsString('Warning!', $response->body);
	}

	/** No row the installer writes stands in for a third-party module's data, so its patches run, batch by batch, once the board's rows are there. */
	public function testAThirdPartyModulesPatchesRunAfterTheBoardsRows(): void {
		$this->controller = $this->installer(new SeedingModule($this->journal));
		$this->post(self::form());

		$entries = $this->journal->entries;
		$this->assertNotContains('record Seeding::seed', array_slice($entries, 0, (int) array_search('ranks New member 0, Member 10', $entries, true)));
		$this->assertSame(array('ranks New member 0, Member 10', 'seed from 0', 'seed from 1', 'record Seeding::seed', 'version Seeding data 1.0.0', 'end transaction'), array_slice($entries, (int) array_search('ranks New member 0, Member 10', $entries, true), 6), 'its data version follows its patches');
		$this->assertContains('record Update::avatars', $entries, 'the forum\'s own patches are still recorded unapplied');
		$this->assertSame(array(), $this->moduleVersions->behind());
	}

	public function testConfigPhpItCouldNotWriteIsOfferedWithWhatToLookAfter(): void {
		$this->files->writable = false;
		$this->files->cache = false;
		$this->environment->uploads = false;

		$body = $this->post(self::form(array('db_password' => 'a"b')))->body;

		$this->assertStringContainsString('<strong>The cache directory is currently not writable!</strong>', $body);
		$this->assertStringContainsString('<strong>File uploads appear to be disallowed on this server!</strong>', $body);
		$this->assertStringContainsString('<input type="hidden" name="generate_config" value="1" />', $body);
		$this->assertStringContainsString('<input type="hidden" name="db_password" value="a&quot;b" />', $body);
		$this->assertStringContainsString('<input type="hidden" name="cookie_name" value="forum_cookie_aaaaaa" />', $body);
		$this->assertSame(array(), $this->journal->starting('clear cache'));
		$this->assertSame('0', $this->board->settings['o_avatars']);
	}

	public function testTheRepositoryIsInstalledWhenAskedAndShipped(): void {
		$this->post(self::form(array('install_pun_repository' => '1')));
		$this->assertSame(array(), $this->journal->starting('extension'), 'the forum does not ship it');

		$this->extensions->shipped = true;
		$this->journal->entries = array();
		$this->post(self::form(array('install_pun_repository' => '1')));

		$this->assertSame(array('extension pun_repository with 1 hooks'), $this->journal->starting('extension'));
		$this->assertLessThan(array_search('close', $this->journal->entries, true), array_search('extension pun_repository with 1 hooks', $this->journal->entries, true));
	}
}
