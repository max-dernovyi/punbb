<?php
/**
 * The registry decides the order modules load in from what each declares — its
 * dependencies and what it loads after — and assembles the container from each
 * module's own wiring.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleException;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\ModuleTree;
use PunBB\Module\Framework\Modules\Wiring;
use PunBBFixture\Module\Greeting\Api\Data\GreetingInterface;
use PunBBFixture\Module\Greeting\Api\GreeterInterface;
use PunBBFixture\Module\Greeting\Controller\GreetingController;
use PunBBFixture\Module\Greeting\Interceptor\GreeterInterceptor;
use PunBBFixture\Module\Greeting\Model\Greeter;
use PunBBFixture\Module\Greeting\Model\Journal;

require_once __DIR__.'/InterceptorProbes.php';

class ModuleRegistryTest extends TestCase {
	private const PROBE_NAMESPACE = 'PunBBModuleRegistryProbe\\';

	private ?string $directory = null;

	private ?Closure $autoloader = null;

	protected function tearDown(): void {
		if ($this->autoloader !== null)
			spl_autoload_unregister($this->autoloader);

		if ($this->directory !== null)
			self::remove($this->directory);
	}

	private static function remove(string $path): void {
		if (is_link($path) || is_file($path))
			unlink($path);
		else if (is_dir($path))
		{
			foreach ((array) scandir($path) as $entry)
				if ($entry !== '.' && $entry !== '..')
					self::remove($path.'/'.$entry);

			rmdir($path);
		}
	}

	/**
	 * @param list<string> $dependencies
	 * @param list<string> $loadAfter
	 * @param (Closure(Wiring): void)|null $wire
	 */
	private static function module(string $name, array $dependencies = array(), array $loadAfter = array(), ?Closure $wire = null, string $version = '1.0.0'): ModuleInterface {
		return new class($name, $dependencies, $loadAfter, $wire, $version) implements ModuleInterface {
			public function __construct(private string $name, private array $dependencies, private array $loadAfter, private ?Closure $wire, private string $version) {}

			public function name(): string {
				return $this->name;
			}

			public function dependencies(): array {
				return $this->dependencies;
			}

			public function loadAfter(): array {
				return $this->loadAfter;
			}

			public function version(): string {
				return $this->version;
			}

			public function wire(Wiring $wiring): void {
				if ($this->wire !== null)
					($this->wire)($wiring);
			}
		};
	}

	/** A directory holding an empty <Name>/Module.php for each name. */
	private function moduleDirectory(string ...$names): string {
		return $this->moduleSources(array_fill_keys($names, ''));
	}

	/**
	 * A directory holding <Name>/Module.php with each source, its classes
	 * autoloaded from it under the probe namespace.
	 *
	 * @param array<string, string> $sources module => the source of its Module.php
	 */
	private function moduleSources(array $sources): string {
		$this->directory = sys_get_temp_dir().'/punbb_modules_'.bin2hex(random_bytes(6));
		mkdir($this->directory);

		foreach ($sources as $name => $source)
		{
			mkdir($this->directory.'/'.$name);
			file_put_contents($this->directory.'/'.$name.'/Module.php', $source);
		}

		$directory = $this->directory;
		$this->autoloader = static function (string $class) use ($directory): void {
			if (str_starts_with($class, self::PROBE_NAMESPACE) && is_file($file = $directory.'/'.str_replace('\\', '/', substr($class, strlen(self::PROBE_NAMESPACE))).'.php') && filesize($file) > 0)
				require $file;
		};
		spl_autoload_register($this->autoloader);

		return $this->directory;
	}

	/**
	 * @param list<ModuleInterface> $thirdParty
	 */
	private static function withThirdParty(array $thirdParty): ModuleRegistry {
		return ModuleRegistry::withThirdParty(array(self::module('Framework'), self::module('Forums', array('Framework'))), $thirdParty);
	}

	/** @param array<string, string> $skipped module => the start of why it was skipped */
	private function assertSkipped(array $skipped, ModuleRegistry $registry): void {
		$this->assertSame(array_keys($skipped), array_keys($registry->skipped()));
		foreach ($skipped as $name => $reason)
			$this->assertStringStartsWith($reason, $registry->skipped()[$name]);
	}

	private function assertRegistryFails(string $message, ModuleInterface ...$modules): void {
		try {
			new ModuleRegistry(...$modules);
			$this->fail('the registry accepted: '.$message);
		}
		catch (ModuleException $e) {
			$this->assertSame($message, $e->getMessage());
		}
	}

	public function testADependencyLoadsBeforeTheModuleNamingIt(): void {
		$registry = new ModuleRegistry(
			self::module('Topics', array('Forums')),
			self::module('Forums', array('Users')),
			self::module('Users')
		);

		$this->assertSame(array('Users', 'Forums', 'Topics'), $registry->names());
	}

	public function testUnconstrainedModulesKeepTheirRegistrationOrder(): void {
		$registry = new ModuleRegistry(self::module('Search'), self::module('Help'), self::module('Admin'));

		$this->assertSame(array('Search', 'Help', 'Admin'), $registry->names());
	}

	public function testLoadAfterOrdersAgainstARegisteredModule(): void {
		$registry = new ModuleRegistry(self::module('Portal', array(), array('Gallery')), self::module('Gallery'));

		$this->assertSame(array('Gallery', 'Portal'), $registry->names());
	}

	public function testLoadAfterIsIgnoredForAnAbsentModule(): void {
		$registry = new ModuleRegistry(self::module('Portal', array(), array('Gallery')));

		$this->assertSame(array('Portal'), $registry->names());
	}

	public function testAnAbsentDependencyFailsNamingBothModules(): void {
		$this->assertRegistryFails('Module Portal depends on Gallery, which is not registered', self::module('Portal', array('Gallery')));
	}

	public function testACycleFailsNamingTheCycle(): void {
		$this->assertRegistryFails(
			'Module load order has a cycle: Alpha -> Beta -> Gamma -> Alpha',
			self::module('Alpha', array('Beta')),
			self::module('Beta', array(), array('Gamma')),
			self::module('Gamma', array('Alpha')),
			self::module('Delta')
		);
	}

	public function testACycleBehindAnAcyclicModuleIsStillNamed(): void {
		$this->assertRegistryFails(
			'Module load order has a cycle: Beta -> Gamma -> Beta',
			self::module('Alpha', array('Beta')),
			self::module('Beta', array('Gamma')),
			self::module('Gamma', array('Beta'))
		);
	}

	public function testAModuleDependingOnItselfIsACycle(): void {
		$this->assertRegistryFails('Module load order has a cycle: Alpha -> Alpha', self::module('Alpha', array('Alpha')));
	}

	public function testAModuleRegisteredTwiceFails(): void {
		$this->assertRegistryFails('Module Users is registered twice', self::module('Users'), self::module('Users'));
	}

	public function testAModuleNameMustBeANamespaceSegment(): void {
		$this->assertRegistryFails('Module name "user-list" is not a namespace segment', self::module('user-list'));
	}

	/** The modules table records a name in 50 characters. */
	public function testAModuleNameIsAtMostFiftyCharactersOnOneLine(): void {
		$this->assertSame(array('P'.str_repeat('o', 49)), (new ModuleRegistry(self::module('P'.str_repeat('o', 49))))->names());
		$this->assertRegistryFails('Module name "'.'P'.str_repeat('o', 50).'" is not a namespace segment', self::module('P'.str_repeat('o', 50)));
		$this->assertRegistryFails("Module name \"Polls\n\" is not a namespace segment", self::module("Polls\n"));
	}

	/** @return array<string, array{string}> */
	public static function malformedVersions(): array {
		return array(
			'empty'			=> array(''),
			'zero'			=> array('0'),
			'zeros'			=> array('0.0.0'),
			'a word'		=> array('1.x'),
			'a prefix'		=> array('v1.0'),
			'a gap'			=> array('1..0'),
			'a pre-release'	=> array('2.0.0-rc1'),
			'a line break'	=> array("1.0.0\n"),
			'51 characters'	=> array(str_repeat('1.', 25).'1'),
		);
	}

	/** Zero is what a board records for a module it never had, so a module declaring it could never be installed. */
	#[DataProvider('malformedVersions')]
	public function testAModuleVersionIsNumbersSeparatedByDotsAboveZero(string $version): void {
		$this->assertRegistryFails('Module Polls declares version "'.$version.'"; a version is numbers separated by dots, above zero', self::module('Polls', version: $version));
	}

	public function testAWellFormedVersionIsAccepted(): void {
		$versions = array('1', '0.1', '10.20.30', str_repeat('1.', 24).'10');
		$this->assertSame($versions, array_map(static fn (string $version): string => (new ModuleRegistry(self::module('Polls', version: $version)))->modules()[0]->version(), $versions));
	}

	public function testTheContainerIsAssembledFromEveryModulesWiringInLoadOrder(): void {
		$wired = array();

		$registry = new ModuleRegistry(
			self::module('Greeting', array('Names'), array(), function (Wiring $wiring) use (&$wired): void {
				$wired[] = 'Greeting';
				$wiring->service('greeting', fn (Container $c): object => new ArrayObject(array('Hello, '.$c->get('name')['value'])));
			}),
			self::module('Names', array(), array(), function (Wiring $wiring) use (&$wired): void {
				$wired[] = 'Names';
				$wiring->service('name', fn (): object => new ArrayObject(array('value' => 'Rick')));
			})
		);

		$container = $registry->container();

		$this->assertSame(array('Names', 'Greeting'), $wired);
		$this->assertSame(array('Hello, Rick'), $container->get('greeting')->getArrayCopy());
	}

	public function testTwoModulesWiringOneServiceFail(): void {
		$wire = fn (Wiring $wiring) => $wiring->service('mailer', fn (): object => new stdClass());
		$registry = new ModuleRegistry(self::module('Smtp', array(), array(), $wire), self::module('Sendmail', array(), array(), $wire));

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Modules Smtp and Sendmail both wire service "mailer"');
		$registry->container();
	}

	public function testTheRegistryIsAServiceOfItsContainerThatNoModuleWires(): void {
		$registry = new ModuleRegistry(self::module('Names'));
		$this->assertSame($registry, $registry->container()->get(ModuleRegistry::class));

		$impostor = new ModuleRegistry(self::module('Names', array(), array(), fn (Wiring $wiring) => $wiring->service(ModuleRegistry::class, fn (): object => new stdClass())));

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module Names wires service "'.ModuleRegistry::class.'", which is the registry itself');
		$impostor->container();
	}

	public function testAModuleWiringOneServiceTwiceFails(): void {
		$registry = new ModuleRegistry(self::module('Smtp', array(), array(), function (Wiring $wiring): void {
			$wiring->service('mailer', fn (): object => new stdClass());
			$wiring->service('mailer', fn (): object => new stdClass());
		}));

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module Smtp wires service "mailer" twice');
		$registry->container();
	}

	/** @return array<string, array{string, string, string}> */
	public static function misplacedContractProvider(): array {
		return array(
			'a class'						=> array('Greeting', Greeter::class, 'not an interface in its Api namespace'),
			'a data interface'				=> array('Greeting', GreetingInterface::class, 'not an interface in its Api namespace'),
			'another module\'s contract'	=> array('Courtesy', GreeterInterface::class, 'not an interface in its Api namespace'),
			'an unknown name'				=> array('Greeting', 'PunBBFixture\\Module\\Greeting\\Api\\MissingInterface', 'not an interface in its Api namespace'),
		);
	}

	#[DataProvider('misplacedContractProvider')]
	public function testAContractIsAnInterfaceDirectlyInTheWiringModulesApiNamespace(string $module, string $contract, string $message): void {
		$registry = new ModuleRegistry(self::module($module, array(), array(), fn (Wiring $wiring) =>
			$wiring->contract($contract, GreeterInterceptor::class, fn (): object => new Greeter(new Journal()))));

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module '.$module.' wires contract '.$contract.', which is '.$message);
		$registry->container();
	}

	/** @return array<string, array{string}> */
	public static function wrongInterceptorProvider(): array {
		return array(
			'the subject itself'		=> array(Greeter::class),
			'not implementing it'		=> array(Journal::class),
			'not a class'				=> array('PunBBFixture\\Module\\Greeting\\Interceptor\\MissingInterceptor'),
			'built from no chain'		=> array(InterceptorProbeWithoutChain::class),
			'chain and subject swapped'	=> array(InterceptorProbeSwapped::class),
			'not built from a chain'	=> array((new class(new Greeter(new Journal())) implements GreeterInterface {
				public function __construct(private GreeterInterface $subject) {}

				public function greet(string $name): GreetingInterface {
					return $this->subject->greet($name);
				}

				public function farewell(string $name): GreetingInterface {
					return $this->subject->farewell($name);
				}
			})::class),
		);
	}

	#[DataProvider('wrongInterceptorProvider')]
	public function testAnInterceptorIsAFinalClassImplementingTheContractBuiltFromItsChain(string $interceptor): void {
		$registry = new ModuleRegistry(self::module('Greeting', array(), array(), fn (Wiring $wiring) =>
			$wiring->contract(GreeterInterface::class, $interceptor, fn (): object => new Greeter(new Journal()))));

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module Greeting wires '.$interceptor.' as the interceptor of '.GreeterInterface::class);
		$registry->container();
	}

	public function testAnApiInterfaceCannotBeWiredAsAPlainService(): void {
		$registry = new ModuleRegistry(self::module('Greeting', array(), array(), fn (Wiring $wiring) =>
			$wiring->service(GreeterInterface::class, fn (): object => new Greeter(new Journal()))));

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module Greeting wires '.GreeterInterface::class.' as a plain service; an Api interface is wired with contract()');
		$registry->container();
	}

	public function testDiscoveryRegistersTheCoreTreesModulesFirst(): void {
		$registry = ModuleRegistry::discover(new ModuleTree(FORUM_ROOT.'.dev/tests/fixtures/modules', 'PunBBFixture\\Module\\'), ModuleTree::core(FORUM_ROOT));

		$this->assertSame(array('Greeting', 'Courtesy'), array_slice($registry->names(), -2));
		$this->assertSame(array(), $registry->skipped());
	}

	public function testDiscoveryRegistersTheForumsModules(): void {
		$registry = ModuleRegistry::discover(ModuleTree::core(FORUM_ROOT));

		$this->assertContains('Framework', $registry->names());
		foreach ($registry->modules() as $module)
			$this->assertSame('PunBB\\Module\\'.$module->name().'\\Module', $module::class);
	}

	public function testDiscoveryFailsOnADirectoryWithoutAModuleClass(): void {
		$directory = $this->moduleDirectory('Ghost');

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('does not declare a module class '.self::PROBE_NAMESPACE.'Ghost\\Module');
		ModuleRegistry::discover(new ModuleTree($directory, self::PROBE_NAMESPACE, core: true));
	}

	public function testDiscoveryFailsOnAModuleNotNamedAfterItsDirectory(): void {
		$misnamed = new class implements ModuleInterface {
			public function name(): string {
				return 'Elsewhere';
			}

			public function dependencies(): array {
				return array();
			}

			public function loadAfter(): array {
				return array();
			}

			public function version(): string {
				return '1.0.0';
			}

			public function wire(Wiring $wiring): void {}
		};
		class_alias($misnamed::class, self::PROBE_NAMESPACE.'Misnamed\\Module');

		$directory = $this->moduleDirectory('Misnamed');

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('is named Elsewhere, not after its directory');
		ModuleRegistry::discover(new ModuleTree($directory, self::PROBE_NAMESPACE, core: true));
	}

	public function testThirdPartyModulesLoadAfterTheCoreOnesAndMayDependOnThem(): void {
		$registry = self::withThirdParty(array(
			self::module('Portal', array(), array('Gallery')),
			self::module('Polls', array('Forums')),
			self::module('Gallery', array('Framework')),
		));

		$this->assertSame(array('Framework', 'Forums', 'Polls', 'Gallery', 'Portal'), $registry->names());
		$this->assertSame(array(), $registry->skipped());
	}

	/** What include/common.php asks before it reads the recorded versions: a core module moves with the release. */
	public function testTheRegisteredThirdPartyModulesAreTold(): void {
		$registry = self::withThirdParty(array(self::module('Votes', array('Polls')), self::module('Polls', array('Forums')), self::module('Gallery', array('Albums'))));

		$this->assertSame(array('Polls', 'Votes'), $registry->thirdParty(), 'in load order, a skipped one left out');
		$this->assertSame(array(), self::withThirdParty(array())->thirdParty());
		$this->assertSame(array(), (new ModuleRegistry(self::module('Framework')))->thirdParty());
	}

	public function testACoreModuleDependingOnAThirdPartyOneIsRefused(): void {
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module Forums depends on Polls, a third-party module; a core module depends on core modules only');
		ModuleRegistry::withThirdParty(array(self::module('Forums', array('Polls'))), array(self::module('Polls')));
	}

	public function testACoreModuleLoadingAfterAThirdPartyOneIsRefused(): void {
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module Forums loads after Polls, a third-party module; a core module is ordered against core modules only');
		ModuleRegistry::withThirdParty(array(self::module('Forums', array(), array('Polls'))), array(self::module('Polls')));
	}

	public function testACoreModuleNamingASkippedThirdPartyOneIsRefusedAllTheSame(): void {
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module Forums depends on Polls, a third-party module; a core module depends on core modules only');
		ModuleRegistry::withThirdParty(array(self::module('Forums', array('Polls'))), array(self::module('Polls', version: '0')));
	}

	public function testAThirdPartyCycleIsSkippedNamingTheCycle(): void {
		$registry = self::withThirdParty(array(
			self::module('Alpha', array('Beta')),
			self::module('Beta', array(), array('Gamma')),
			self::module('Gamma', array('Alpha')),
			self::module('Delta'),
			self::module('Epsilon', array('Beta')),
			self::module('Zeta', array(), array('Gamma')),
		));

		$cycle = 'Module load order has a cycle: Alpha -> Beta -> Gamma -> Alpha';
		$this->assertSame(array('Framework', 'Forums', 'Delta', 'Zeta'), $registry->names());
		$this->assertSame(array(
			'Alpha'		=> $cycle,
			'Beta'		=> $cycle,
			'Gamma'		=> $cycle,
			'Epsilon'	=> 'Module Epsilon depends on Beta, which is skipped',
		), $registry->skipped());
	}

	public function testAThirdPartyModuleMissingADependencyIsSkippedWithEveryModuleDependingOnIt(): void {
		$registry = self::withThirdParty(array(self::module('Votes', array('Polls')), self::module('Polls', array('Gallery')), self::module('Help')));

		$this->assertSame(array('Framework', 'Forums', 'Help'), $registry->names());
		$this->assertSame(array(
			'Polls'	=> 'Module Polls depends on Gallery, which is not registered',
			'Votes'	=> 'Module Votes depends on Polls, which is skipped',
		), $registry->skipped());
	}

	/** @return array<string, array{Closure(): list<ModuleInterface>, string, string}> */
	public static function undeclaredModuleProvider(): array {
		return array(
			'a malformed name'		=> array(static fn (): array => array(self::module('poll-s')), 'poll-s', 'Module name "poll-s" is not a namespace segment'),
			'a malformed version'	=> array(static fn (): array => array(self::module('Polls', version: '1.x')), 'Polls', 'Module Polls declares version "1.x"; a version is numbers separated by dots, above zero'),
			'a core name'			=> array(static fn (): array => array(self::module('Forums')), 'Forums', 'Module Forums is registered twice'),
			'a name taken before'	=> array(static fn (): array => array(self::module('Help'), self::module('Help', array('Forums'))), 'Help', 'Module Help is registered twice'),
			'a throwing declaration' => array(static fn (): array => array(new class implements ModuleInterface {
				public function name(): string {
					return 'Polls';
				}

				public function dependencies(): array {
					throw new RuntimeException('no dependencies today');
				}

				public function loadAfter(): array {
					return array();
				}

				public function version(): string {
					return '1.0.0';
				}

				public function wire(Wiring $wiring): void {}
			}), 'Polls', 'Module Polls throws RuntimeException: no dependencies today in '),
			'a dependency not named'	=> array(static fn (): array => array(self::module('Polls', array(array()))), 'Polls', 'Module Polls lists array in dependencies(); it lists module names'),
			'a predecessor not named'	=> array(static fn (): array => array(self::module('Polls', array(), array(7))), 'Polls', 'Module Polls lists int in loadAfter(); it lists module names'),
		);
	}

	/** @param Closure(): list<ModuleInterface> $modules */
	#[DataProvider('undeclaredModuleProvider')]
	public function testAThirdPartyModuleThatDoesNotDeclareItselfIsSkipped(Closure $modules, string $name, string $reason): void {
		$registry = self::withThirdParty($modules());

		$this->assertSkipped(array($name => $reason), $registry);
		$this->assertSame(array('Framework', 'Forums'), array_values(array_diff($registry->names(), array('Help'))));
	}

	/** @return array<string, array{Closure(Wiring): void, string}> */
	public static function unwirableModuleProvider(): array {
		return array(
			'a throwing wiring'		=> array(static function (Wiring $wiring): void {
				throw new RuntimeException('no wiring today');
			}, 'Module Polls throws RuntimeException: no wiring today in '),
			'a core service'		=> array(static fn (Wiring $wiring) => $wiring->service('mailer', fn (): object => new stdClass()), 'Modules Framework and Polls both wire service "mailer"'),
			'the dispatcher'		=> array(static fn (Wiring $wiring) => $wiring->service(EventDispatcher::class, fn (): object => new stdClass()), 'Module Polls wires service "'.EventDispatcher::class.'", which the registry provides'),
			'a core route'			=> array(static fn (Wiring $wiring) => $wiring->route(array('index.php'), GreetingController::class, fn (): object => new stdClass()), 'Modules Forums and Polls both route "index.php"'),
			'an unwired contract'	=> array(static fn (Wiring $wiring) => $wiring->plugin(GreeterInterface::class, stdClass::class, fn (): object => new stdClass()), 'Module Polls plugs '.GreeterInterface::class.', which no module wires as a contract'),
			'a wrong observer'		=> array(static fn (Wiring $wiring) => $wiring->observer(stdClass::class, stdClass::class, fn (): object => new stdClass()), 'Module Polls observes stdClass, which is not a final class implementing'),
		);
	}

	/**
	 * Nothing of a skipped module is wired, and neither is a module depending on it.
	 *
	 * @param Closure(Wiring): void $wire
	 */
	#[DataProvider('unwirableModuleProvider')]
	public function testAThirdPartyModuleWhoseWiringIsRefusedIsSkippedWithItsDependents(Closure $wire, string $reason): void {
		$registry = ModuleRegistry::withThirdParty(array(
			self::module('Framework', array(), array(), fn (Wiring $wiring) => $wiring->service('mailer', fn (): object => new ArrayObject(array('core')))),
			self::module('Forums', array('Framework'), array(), fn (Wiring $wiring) => $wiring->route(array('index.php'), GreetingController::class, fn (): object => new stdClass())),
		), array(
			self::module('Polls', array('Forums'), array(), function (Wiring $wiring) use ($wire): void {
				$wiring->service('poll', fn (): object => new stdClass());
				$wiring->route(array('poll.php'), GreetingController::class, fn (): object => new stdClass());
				$wire($wiring);
			}),
			self::module('Votes', array('Polls')),
			self::module('Help', array(), array(), fn (Wiring $wiring) => $wiring->service('help', fn (): object => new ArrayObject(array('help')))),
		));

		$this->assertSame(array('Framework', 'Forums', 'Help'), $registry->names());
		$this->assertSkipped(array('Polls' => $reason, 'Votes' => 'Module Votes depends on Polls, which is skipped'), $registry);

		$container = $registry->container();
		$this->assertFalse($container->has('poll'));
		$this->assertSame(array('core'), $container->get('mailer')->getArrayCopy());
		$this->assertSame(array('help'), $container->get('help')->getArrayCopy());
		$this->assertNull($registry->router()->match('poll.php'));
		$this->assertSame('Forums', $registry->router()->match('index.php')?->module);
	}

	public function testAThirdPartyModuleIsWiredAsACoreOneIs(): void {
		$registry = self::withThirdParty(array(self::module('Polls', array('Forums'), array(), function (Wiring $wiring): void {
			$wiring->service('poll', fn (): object => new ArrayObject(array('poll')));
			$wiring->route(array('poll.php'), GreetingController::class, fn (): object => new stdClass());
		})));

		$this->assertSame(array('poll'), $registry->container()->get('poll')->getArrayCopy());
		$this->assertSame('Polls', $registry->router()->match('poll.php')?->module);
	}

	public function testAThirdPartyTreeSkipsWhatDoesNotLoad(): void {
		$directory = $this->moduleSources(array(
			'Ghost'		=> '',
			'Garbled'	=> "<?php\nnamespace PunBBModuleRegistryProbe\\Garbled;\nfinal class Module {\n",
			'Throwing'	=> "<?php\nnamespace PunBBModuleRegistryProbe\\Throwing;\nfinal class Module implements \\PunBB\\Module\\Framework\\Modules\\ModuleInterface {\n"
				."\tpublic function __construct() { throw new \\RuntimeException('not today'); }\n"
				."\tpublic function name(): string { return 'Throwing'; }\n"
				."\tpublic function dependencies(): array { return array(); }\n"
				."\tpublic function loadAfter(): array { return array(); }\n"
				."\tpublic function version(): string { return '1.0.0'; }\n"
				."\tpublic function wire(\\PunBB\\Module\\Framework\\Modules\\Wiring \$wiring): void {}\n}\n",
			'Loaded'	=> "<?php\nnamespace PunBBModuleRegistryProbe\\Loaded;\nfinal class Module implements \\PunBB\\Module\\Framework\\Modules\\ModuleInterface {\n"
				."\tpublic function name(): string { return 'Loaded'; }\n"
				."\tpublic function dependencies(): array { return array('Framework'); }\n"
				."\tpublic function loadAfter(): array { return array(); }\n"
				."\tpublic function version(): string { return '1.0.0'; }\n"
				."\tpublic function wire(\\PunBB\\Module\\Framework\\Modules\\Wiring \$wiring): void {}\n}\n",
		));

		$registry = ModuleRegistry::discover(ModuleTree::core(FORUM_ROOT), new ModuleTree($directory, self::PROBE_NAMESPACE));

		$this->assertSame('Loaded', array_slice($registry->names(), -1)[0]);
		$this->assertSkipped(array(
			'Garbled'	=> 'Module Garbled throws ParseError: ',
			'Ghost'		=> $directory.'/Ghost/Module.php does not declare a module class '.self::PROBE_NAMESPACE.'Ghost\\Module',
			'Throwing'	=> 'Module Throwing throws RuntimeException: not today in '.$directory.'/Throwing/Module.php',
		), $registry);
	}

	/** Each request discovers twice; a file that declared something else is not included a second time. */
	public function testAThirdPartyModuleDeclaringAnotherClassIsSkippedOnEveryDiscovery(): void {
		$directory = $this->moduleSources(array(
			'Stray'	=> "<?php\nnamespace PunBBModuleRegistryProbe\\Elsewhere;\nfinal class Module {}\nfunction stray(): void {}\n",
		));

		foreach (array(1, 2) as $discovery)
		{
			$registry = ModuleRegistry::discover(ModuleTree::core(FORUM_ROOT), new ModuleTree($directory, self::PROBE_NAMESPACE));
			$this->assertSkipped(array('Stray' => $directory.'/Stray/Module.php does not declare a module class '.self::PROBE_NAMESPACE.'Stray\\Module'), $registry);
		}
	}

	/** The forum's root discovers its own tree and modules/, and hands every skipped module to the report. */
	public function testTheForumReportsEachModuleItSkips(): void {
		$root = $this->moduleDirectory('Ghost');
		mkdir($root.'/modules');
		rename($root.'/Ghost', $root.'/modules/Ghost');
		symlink(FORUM_ROOT.'include', $root.'/include');

		$reported = array();
		$registry = ModuleRegistry::forum($root.'/', function (string $line) use (&$reported): void {
			$reported[] = $line;
		});

		$this->assertSame(ModuleRegistry::discover(ModuleTree::core(FORUM_ROOT))->names(), $registry->names());
		$this->assertSame(array('PunBB module skipped: '.$root.'/modules/Ghost/Module.php does not declare a module class PunBBModule\\Ghost\\Module'), $reported);
	}

	/** An archive unpacked without modules/ has no third-party module, and nothing to report. */
	public function testAForumWithoutAModulesDirectoryRegistersItsOwn(): void {
		$root = $this->moduleDirectory();
		symlink(FORUM_ROOT.'include', $root.'/include');

		$registry = ModuleRegistry::forum($root.'/', function (string $line): void {
			$this->fail('Reported: '.$line);
		});

		$this->assertSame(ModuleRegistry::discover(ModuleTree::core(FORUM_ROOT))->names(), $registry->names());
		$this->assertSame(array(), $registry->thirdParty());
	}
}
