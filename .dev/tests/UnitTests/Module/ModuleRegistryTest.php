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
use PunBB\Module\Framework\Modules\ModuleException;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\Wiring;
use PunBBFixture\Module\Greeting\Api\Data\GreetingInterface;
use PunBBFixture\Module\Greeting\Api\GreeterInterface;
use PunBBFixture\Module\Greeting\Interceptor\GreeterInterceptor;
use PunBBFixture\Module\Greeting\Model\Greeter;
use PunBBFixture\Module\Greeting\Model\Journal;

require_once __DIR__.'/InterceptorProbes.php';

class ModuleRegistryTest extends TestCase {
	private const PROBE_NAMESPACE = 'PunBBModuleRegistryProbe\\';

	private ?string $directory = null;

	protected function tearDown(): void {
		if ($this->directory === null)
			return;

		foreach ((array) glob($this->directory.'/*/Module.php') as $file)
		{
			unlink((string) $file);
			rmdir(dirname((string) $file));
		}

		rmdir($this->directory);
	}

	/**
	 * @param list<string> $dependencies
	 * @param list<string> $loadAfter
	 * @param (Closure(Wiring): void)|null $wire
	 */
	private static function module(string $name, array $dependencies = array(), array $loadAfter = array(), ?Closure $wire = null): ModuleInterface {
		return new class($name, $dependencies, $loadAfter, $wire) implements ModuleInterface {
			public function __construct(private string $name, private array $dependencies, private array $loadAfter, private ?Closure $wire) {}

			public function name(): string {
				return $this->name;
			}

			public function dependencies(): array {
				return $this->dependencies;
			}

			public function loadAfter(): array {
				return $this->loadAfter;
			}

			public function wire(Wiring $wiring): void {
				if ($this->wire !== null)
					($this->wire)($wiring);
			}
		};
	}

	/** A directory holding an empty <Name>/Module.php for each name. */
	private function moduleDirectory(string ...$names): string {
		$this->directory = sys_get_temp_dir().'/punbb_modules_'.bin2hex(random_bytes(6));
		mkdir($this->directory);

		foreach ($names as $name)
		{
			mkdir($this->directory.'/'.$name);
			touch($this->directory.'/'.$name.'/Module.php');
		}

		return $this->directory;
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

	public function testDiscoveryRegistersAnotherTreesModulesFirst(): void {
		$registry = ModuleRegistry::discover(FORUM_ROOT.'.dev/tests/fixtures/modules', 'PunBBFixture\\Module\\', self::module('Framework'));

		$this->assertSame(array('Framework', 'Greeting', 'Courtesy'), $registry->names());
	}

	public function testDiscoveryRegistersTheForumsModules(): void {
		$registry = ModuleRegistry::discover(FORUM_ROOT.'include/PunBB/Module', 'PunBB\\Module\\');

		$this->assertContains('Framework', $registry->names());
		foreach ($registry->modules() as $module)
			$this->assertSame('PunBB\\Module\\'.$module->name().'\\Module', $module::class);
	}

	public function testDiscoveryFailsOnADirectoryWithoutAModuleClass(): void {
		$directory = $this->moduleDirectory('Ghost');

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('does not declare a module class '.self::PROBE_NAMESPACE.'Ghost\\Module');
		ModuleRegistry::discover($directory, self::PROBE_NAMESPACE);
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

			public function wire(Wiring $wiring): void {}
		};
		class_alias($misnamed::class, self::PROBE_NAMESPACE.'Misnamed\\Module');

		$directory = $this->moduleDirectory('Misnamed');

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('is named Elsewhere, not after its directory');
		ModuleRegistry::discover($directory, self::PROBE_NAMESPACE);
	}
}
