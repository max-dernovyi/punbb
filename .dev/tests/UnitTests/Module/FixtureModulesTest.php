<?php
/**
 * The fixture modules under .dev/tests/fixtures/modules, wired by the real
 * registry: Greeting owns the greeter contract and the sending event, and plugs
 * and observes them; Courtesy depends on Greeting and plugs the same method and
 * observes the same event.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Module as FrameworkModule;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBBFixture\Module\Courtesy\Module as CourtesyModule;
use PunBBFixture\Module\Greeting\Api\GreeterInterface;
use PunBBFixture\Module\Greeting\Interceptor\GreeterInterceptor;
use PunBBFixture\Module\Greeting\Model\Greeting;
use PunBBFixture\Module\Greeting\Model\Journal;
use PunBBFixture\Module\Greeting\Model\Postman;
use PunBBFixture\Module\Greeting\Module as GreetingModule;

class FixtureModulesTest extends TestCase {
	private static function container(ModuleInterface ...$modules): Container {
		return (new ModuleRegistry(new FrameworkModule(), ...$modules))->container();
	}

	/** @return list<string> */
	private static function journal(Container $container): array {
		return $container->get(Journal::class)->entries();
	}

	public function testTheFixtureTreeIsDiscoveredOnTopOfTheForumsModules(): void {
		$registry = ModuleRegistry::discover(
			FORUM_ROOT.'.dev/tests/fixtures/modules',
			'PunBBFixture\\Module\\',
			...ModuleRegistry::discover(FORUM_ROOT.'include/PunBB/Module', 'PunBB\\Module\\')->modules()
		);

		$this->assertSame(array('Framework', 'LegacyBridge', 'Greeting', 'Courtesy'), $registry->names());
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
