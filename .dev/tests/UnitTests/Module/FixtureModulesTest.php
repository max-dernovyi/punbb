<?php
/**
 * The fixture modules under .dev/tests/fixtures/modules, wired by the real
 * registry and discovered as a third-party tree: Greeting owns the greeter
 * contract, the sending event and a table, and plugs and observes them;
 * Courtesy depends on Greeting and plugs the same method and observes the same
 * event.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Module as DatabaseModule;
use PunBB\Module\Database\Schema\DeclaredSchema;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Database\Version\InstalledVersionsInterface;
use PunBB\Module\Database\Version\ModuleVersions;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Module as FrameworkModule;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\ModuleTree;
use PunBBFixture\Module\Courtesy\Module as CourtesyModule;
use PunBBFixture\Module\Greeting\Api\GreeterInterface;
use PunBBFixture\Module\Greeting\Interceptor\GreeterInterceptor;
use PunBBFixture\Module\Greeting\Model\Greeting;
use PunBBFixture\Module\Greeting\Model\Journal;
use PunBBFixture\Module\Greeting\Model\Postman;
use PunBBFixture\Module\Greeting\Module as GreetingModule;

class FixtureModulesTest extends TestCase {
	private static function container(ModuleInterface ...$modules): Container {
		return (new ModuleRegistry(new FrameworkModule(), new DatabaseModule(), ...$modules))->container();
	}

	/** @return list<string> */
	private static function journal(Container $container): array {
		return $container->get(Journal::class)->entries();
	}

	private static function discovered(): ModuleRegistry {
		return ModuleRegistry::discover(ModuleTree::core(FORUM_ROOT), new ModuleTree(FORUM_ROOT.'.dev/tests/fixtures/modules', 'PunBBFixture\\Module\\'));
	}

	public function testTheFixtureTreeIsDiscoveredAsThirdPartyAfterTheForumsModules(): void {
		$registry = self::discovered();

		$this->assertSame(array('Framework', 'Database', 'Layout', 'Setup', 'Site', 'Extern', 'Install', 'Message', 'AdminIndex', 'Bans', 'Categories', 'Censoring', 'Delete', 'Edit', 'Extensions', 'Forums', 'Groups', 'Help', 'Index', 'Login', 'Misc', 'Moderate', 'Post', 'Profile', 'Prune', 'Ranks', 'Register', 'Reindex', 'Reports', 'Search', 'Settings', 'Update', 'Userlist', 'Users', 'Viewforum', 'Viewtopic', 'LegacyBridge', 'Greeting', 'Courtesy'), $registry->names());
		$this->assertSame(array(), $registry->skipped());
	}

	/** Found outside the core tree, it still wires services, contracts, plugins, observers and routes, owns tables and declares a version. */
	public function testADiscoveredThirdPartyModuleIsAModuleInEverySense(): void {
		$registry = self::discovered();
		$container = $registry->container();

		$this->assertSame('Hello, Dr. Rick. Welcome back!', $container->get(GreeterInterface::class)->greet(' rick ')->text());
		$this->assertSame('Hello, Rick -- the forum P.S. Mind the gap.', $container->get(Postman::class)->send(new Greeting('Rick', 'Hello, Rick'))->text());
		$this->assertSame('Greeting', $registry->router()->match('greeting.php')?->module);

		$tables = $container->get(DeclaredSchema::class)->tablesOf('Greeting', Platform::ofDbType('sqlite3'));
		$this->assertSame(array('greetings'), array_map(static fn (Table $table): string => $table->name, $tables));

		$versions = new ModuleVersions(new class implements InstalledVersionsInterface {
			public function all(): array {
				return array();
			}

			public function recordSchema(string $module, string $version): void {}

			public function recordData(string $module, string $version): void {}
		}, ...$registry->modules());

		$this->assertSame(array('Greeting' => '1.0.0', 'Courtesy' => '1.0.0'), array_slice($versions->declared(), -2, null, true));
		$this->assertSame(array('Greeting', 'Courtesy'), array_slice($versions->behindOnSchema(), -2));
	}

	public function testPluginsOnOneMethodRunInModuleOrderWhateverTheRegistrationOrder(): void {
		$container = self::container(new CourtesyModule(), new GreetingModule());

		$container->get(GreeterInterface::class)->greet(' rick ');

		$this->assertSame(array(
			'Greeting before( rick )',
			'Courtesy before(Rick)',
			'Greeter::greet(Dr. Rick)',
			'Greeting after(Dr. Rick)',
			'Courtesy after',
		), self::journal($container));
	}

	public function testARewrittenArgumentReachesTheSubject(): void {
		$greeting = self::container(new GreetingModule(), new CourtesyModule())->get(GreeterInterface::class)->greet(' rick ');

		$this->assertSame('Dr. Rick', $greeting->recipient());
	}

	public function testARewrittenReturnReachesTheCaller(): void {
		$greeting = self::container(new GreetingModule(), new CourtesyModule())->get(GreeterInterface::class)->greet(' rick ');

		$this->assertSame('Hello, Dr. Rick. Welcome back!', $greeting->text());
	}

	public function testAMethodNoPluginNamesReachesTheSubjectUntouched(): void {
		$container = self::container(new GreetingModule(), new CourtesyModule());

		$this->assertSame('Goodbye,  rick ', $container->get(GreeterInterface::class)->farewell(' rick ')->text());
		$this->assertSame(array('Greeter::farewell( rick )'), self::journal($container));
	}

	public function testWithoutTheSecondModuleOnlyTheFirstPluginRuns(): void {
		$container = self::container(new GreetingModule());

		$greeting = $container->get(GreeterInterface::class)->greet(' rick ');

		$this->assertSame('Hello, Rick.', $greeting->text());
		$this->assertSame(array('Greeting before( rick )', 'Greeter::greet(Rick)', 'Greeting after(Rick)'), self::journal($container));
	}

	public function testTheContractIsOneSharedInterceptedInstance(): void {
		$container = self::container(new GreetingModule(), new CourtesyModule());

		$this->assertSame($container->get(GreeterInterface::class), $container->get(GreeterInterface::class));
		$this->assertInstanceOf(GreeterInterceptor::class, $container->get(GreeterInterface::class));
	}

	public function testObserversOfOneEventRunInModuleOrderAndTheSecondSeesTheFirstsChange(): void {
		$container = self::container(new CourtesyModule(), new GreetingModule());

		$sent = $container->get(Postman::class)->send(new Greeting('Rick', 'Hello, Rick'));

		$this->assertSame(array(
			'Greeting observed(Hello, Rick)',
			'Courtesy observed(Hello, Rick -- the forum)',
		), self::journal($container));
		$this->assertSame('Hello, Rick -- the forum P.S. Mind the gap.', $sent->text());
		$this->assertSame('Rick', $sent->recipient());
	}

	public function testWithoutTheSecondModuleOnlyTheFirstObserverRuns(): void {
		$container = self::container(new GreetingModule());

		$sent = $container->get(Postman::class)->send(new Greeting('Rick', 'Hello, Rick'));

		$this->assertSame('Hello, Rick -- the forum', $sent->text());
		$this->assertSame(array('Greeting observed(Hello, Rick)'), self::journal($container));
	}
}
