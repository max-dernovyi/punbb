<?php
/**
 * An event reaches the observers bound to its class, in module order then
 * declaration order, each handed the event alone. A binding that does not fit
 * its event fails the container assembly; an observer reaching past what the
 * event declares fails the dispatch.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Event\EventException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Framework\Event\ObserverDeclaration;
use PunBB\Module\Framework\Modules\ModuleException;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\Wiring;
use PunBBFixture\Module\Greeting\Event\GreetingSending;
use PunBBFixture\Module\Greeting\Model\Greeting;

class EventDispatcherTest extends TestCase {
	/** An observer that logs the text it was handed and appends $mark to it. */
	private static function marker(ArrayObject $log, string $mark): object {
		return new class($log, $mark) {
			public function __construct(private ArrayObject $log, private string $mark) {}

			public function observe(GreetingSending $event): void {
				$this->log[] = $this->mark.' saw "'.$event->greeting()->text().'"';
				$event->reword($event->greeting()->text().' '.$this->mark);
			}
		};
	}

	/** @param list<object> $observers bound to $event, in this order */
	private static function dispatcher(array $observers, string $event = GreetingSending::class): EventDispatcher {
		$declarations = array();
		foreach ($observers as $observer)
			$declarations[] = new ObserverDeclaration('Probe', $event, $observer::class, fn (): object => $observer);

		return new EventDispatcher($declarations, new Container(array()));
	}

	private static function sending(): GreetingSending {
		return new GreetingSending(new Greeting('Rick', 'Hello, Rick'));
	}

	/**
	 * @param list<string> $dependencies
	 * @param Closure(Wiring): void $wire
	 */
	private static function module(string $name, array $dependencies, Closure $wire): ModuleInterface {
		return new class($name, $dependencies, $wire) implements ModuleInterface {
			public function __construct(private string $name, private array $dependencies, private Closure $wire) {}

			public function name(): string {
				return $this->name;
			}

			public function dependencies(): array {
				return $this->dependencies;
			}

			public function loadAfter(): array {
				return array();
			}

			public function wire(Wiring $wiring): void {
				($this->wire)($wiring);
			}
		};
	}

	public function testObserversRunInOrderAndEachSeesTheChangesBeforeIt(): void {
		$log = new ArrayObject();
		$event = self::sending();

		self::dispatcher(array(self::marker($log, 'first'), self::marker($log, 'second')))->dispatch($event);

		$this->assertSame(array('first saw "Hello, Rick"', 'second saw "Hello, Rick first"'), $log->getArrayCopy());
		$this->assertSame('Hello, Rick first second', $event->greeting()->text());
	}

	public function testAnEventReachesOnlyTheObserversOfItsClass(): void {
		$log = new ArrayObject();
		$other = new class implements EventInterface {};
		$otherObserver = new class($log) {
			public function __construct(private ArrayObject $log) {}

			public function observe(EventInterface $event): void {
				$this->log[] = 'other';
			}
		};
		$dispatcher = new EventDispatcher(array(
			new ObserverDeclaration('Probe', GreetingSending::class, self::marker($log, 'sending')::class, fn (): object => self::marker($log, 'sending')),
			new ObserverDeclaration('Probe', $other::class, $otherObserver::class, fn (): object => $otherObserver),
		), new Container(array()));

		$dispatcher->dispatch($other);
		$this->assertSame(array('other'), $log->getArrayCopy());

		$dispatcher->dispatch(self::sending());
		$this->assertSame(array('other', 'sending saw "Hello, Rick"'), $log->getArrayCopy());
	}

	public function testObserversAreBuiltOnceOnTheirEventsFirstDispatch(): void {
		$built = 0;
		$observer = self::marker(new ArrayObject(), 'once');
		$dispatcher = new EventDispatcher(array(new ObserverDeclaration('Probe', GreetingSending::class, $observer::class, function () use (&$built, $observer): object {
			$built++;
			return $observer;
		})), new Container(array()));

		$this->assertSame(0, $built);

		$dispatcher->dispatch(self::sending());
		$dispatcher->dispatch(self::sending());

		$this->assertSame(1, $built);
	}

	public function testAFactoryBuildingAnotherClassFails(): void {
		$observer = self::marker(new ArrayObject(), 'unused');
		$dispatcher = new EventDispatcher(array(new ObserverDeclaration('Probe', GreetingSending::class, $observer::class, fn (): object => new stdClass())), new Container(array()));

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module Probe wired observer '.$observer::class.' to a stdClass');
		$dispatcher->dispatch(self::sending());
	}

	public function testAnObserverAddingAPropertyFailsBeforeTheNextObserverRuns(): void {
		$log = new ArrayObject();
		$bag = new class {
			public function observe(GreetingSending $event): void {
				@$event->postscript = 'P.S.';
			}
		};

		try {
			self::dispatcher(array($bag, self::marker($log, 'next')))->dispatch(self::sending());
			$this->fail('an observer added a property to the event');
		}
		catch (EventException $e) {
			$this->assertSame($bag::class.' added $postscript to '.GreetingSending::class.'; an observer changes an event only through the methods its class declares', $e->getMessage());
		}

		$this->assertSame(array(), $log->getArrayCopy());
	}

	public function testObserversRunInModuleOrderThenDeclarationOrder(): void {
		$log = new ArrayObject();
		$bind = fn (string ...$marks): Closure => function (Wiring $wiring) use ($log, $marks): void {
			foreach ($marks as $mark)
			{
				$observer = self::marker($log, $mark);
				$wiring->observer(GreetingSending::class, $observer::class, fn (): object => $observer);
			}
		};

		$container = (new ModuleRegistry(
			self::module('Late', array('Early'), $bind('late1', 'late2')),
			self::module('Early', array(), $bind('early'))
		))->container();

		$event = self::sending();
		$container->get(EventDispatcher::class)->dispatch($event);

		$this->assertSame('Hello, Rick early late1 late2', $event->greeting()->text());
	}

	public function testTheContainerHasOneDispatcherWhateverTheModulesBind(): void {
		$container = (new ModuleRegistry())->container();

		$this->assertInstanceOf(EventDispatcher::class, $container->get(EventDispatcher::class));
		$this->assertSame($container->get(EventDispatcher::class), $container->get(EventDispatcher::class));
	}

	public function testAModuleCannotWireTheDispatcherItself(): void {
		$registry = new ModuleRegistry(self::module('Events', array(), fn (Wiring $wiring) =>
			$wiring->service(EventDispatcher::class, fn (Container $c): object => new EventDispatcher(array(), $c))));

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module Events wires service "'.EventDispatcher::class.'", which the registry assembles from every module\'s observers');
		$registry->container();
	}

	/** @return array<string, array{string}> */
	public static function notAnEventProvider(): array {
		return array(
			'a plain class'		=> array(stdClass::class),
			'an unknown name'	=> array('Nowhere\\Event'),
			'not final'			=> array((new class implements EventInterface {})::class),
			'the interface'		=> array(EventInterface::class),
		);
	}

	#[DataProvider('notAnEventProvider')]
	public function testAnObserverBindsToAFinalEventClass(string $event): void {
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module Probe observes '.$event.', which is not a final class implementing '.EventInterface::class);
		(new Wiring('Probe'))->observer($event, self::marker(new ArrayObject(), 'unused')::class, fn (): object => new stdClass());
	}

	/** @return array<string, array{string}> */
	public static function wrongObserverProvider(): array {
		return array(
			'not a class'			=> array('Nowhere\\Observer'),
			'no observe()'			=> array((new class {})::class),
			'a wider event type'	=> array((new class {
				public function observe(EventInterface $event): void {}
			})::class),
			'a nullable event'		=> array((new class {
				public function observe(?GreetingSending $event): void {}
			})::class),
			'a second parameter'	=> array((new class {
				public function observe(GreetingSending $event, string $note = ''): void {}
			})::class),
			'a result'				=> array((new class {
				public function observe(GreetingSending $event): bool {
					return true;
				}
			})::class),
			'static'				=> array((new class {
				public static function observe(GreetingSending $event): void {}
			})::class),
			'not public'			=> array((new class {
				protected function observe(GreetingSending $event): void {}
			})::class),
		);
	}

	#[DataProvider('wrongObserverProvider')]
	public function testAnObserverDeclaresObserveTakingItsEventAlone(string $observer): void {
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module Probe binds '.$observer.' to '.GreetingSending::class.'; it must declare observe('.GreetingSending::class.' $event): void');
		(new Wiring('Probe'))->observer(GreetingSending::class, $observer, fn (): object => new stdClass());
	}

	public function testAModuleWithAWrongBindingFailsTheContainerAssembly(): void {
		$registry = new ModuleRegistry(self::module('Probe', array(), fn (Wiring $wiring) =>
			$wiring->observer(GreetingSending::class, stdClass::class, fn (): object => new stdClass())));

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module Probe binds stdClass to '.GreetingSending::class);
		$registry->container();
	}
}
