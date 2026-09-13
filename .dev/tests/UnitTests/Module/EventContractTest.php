<?php
/**
 * An event is a final class whose public methods are all an observer can read
 * and change: no property it can write, no magic, no array access, no
 * reference into its state, and nothing but scalars and Api\Data interfaces
 * through its surface.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Event\EventInterface;
use PunBBFixture\Module\Greeting\Event\GreetingSending;

require_once __DIR__.'/BoundaryTypes.php';

class EventContractTest extends TestCase {
	private const BROKEN = 'PunBBFixture\\BrokenEvent\\';

	public static function setUpBeforeClass(): void {
		require_once FORUM_ROOT.'.dev/tests/fixtures/events/broken_events.php';
	}

	/** @return array<string, array{string, string}> */
	public static function treeProvider(): array {
		return array(
			'forum'		=> array(FORUM_ROOT.'include/PunBB', 'PunBB\\'),
			'fixtures'	=> array(FORUM_ROOT.'.dev/tests/fixtures/modules', 'PunBBFixture\\Module\\'),
		);
	}

	/** @return list<string> every class below $directory implementing EventInterface, by its path */
	private static function events(string $directory, string $namespace): array {
		$events = array();
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file)
		{
			$class = $namespace.str_replace('/', '\\', substr($file->getPathname(), strlen($directory) + 1, -4));
			if (class_exists($class) && is_subclass_of($class, EventInterface::class))
				$events[] = $class;
		}

		sort($events);

		return $events;
	}

	/**
	 * What in an event lets an observer reach past the methods its class declares.
	 *
	 * @return list<string>
	 */
	private static function eventProblems(string $event): array {
		$class = new ReflectionClass($event);
		$name = $class->getShortName();
		$problems = array();

		if (!$class->isFinal())
			$problems[] = $name.' is not final';

		if ($class->getAttributes(AllowDynamicProperties::class) !== array())
			$problems[] = $name.' allows dynamic properties';

		foreach (array(ArrayAccess::class, Traversable::class) as $interface)
			if ($class->implementsInterface($interface))
				$problems[] = $name.' implements '.$interface;

		foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property)
		{
			// readonly implies protected(set)
			if ($property->isStatic() || (!$property->isPrivateSet() && !$property->isProtectedSet()))
				$problems[] = $name.'::$'.$property->getName().' is publicly settable';

			array_push($problems, ...BoundaryTypes::propertyProblems($class, $property));
		}

		foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
		{
			if ($method->isConstructor())
				continue;

			$where = $name.'::'.$method->getName().'()';

			if (str_starts_with($method->getName(), '__'))
			{
				$problems[] = $where.' is a magic method';
				continue;
			}

			if ($method->returnsReference())
				$problems[] = $where.' returns by reference';

			foreach ($method->getParameters() as $parameter)
			{
				if ($parameter->isPassedByReference())
					$problems[] = $where.' takes $'.$parameter->getName().' by reference';

				array_push($problems, ...BoundaryTypes::parameterProblems($class, $method, $parameter));
			}

			array_push($problems, ...BoundaryTypes::returnProblems($class, $method));
		}

		return $problems;
	}

	#[DataProvider('treeProvider')]
	public function testEveryEventDeclaresAllAnObserverCanReach(string $directory, string $namespace): void {
		$problems = array();
		foreach (self::events($directory, $namespace) as $event)
			array_push($problems, ...self::eventProblems($event));

		$this->assertSame(array(), $problems, implode("\n", $problems));
	}

	/** The fixtures are what gives the rules above something to check. */
	public function testTheFixtureTreeHasAnEventToCheck(): void {
		$tree = self::treeProvider()['fixtures'];

		$this->assertSame(array(GreetingSending::class), self::events($tree[0], $tree[1]));
	}

	public function testTheRulesAcceptReadOnlyPropertiesMethodsAndListsOfDataInterfaces(): void {
		$this->assertSame(array(), self::eventProblems(self::BROKEN.'TypedEvent'));
	}

	/** @return array<string, array{string, list<string>}> */
	public static function brokenEventProvider(): array {
		return array(
			'not final'					=> array('OpenEvent', array('OpenEvent is not final')),
			'dynamic properties'		=> array('BagEvent', array('BagEvent allows dynamic properties')),
			'__get'						=> array('MagicEvent', array('MagicEvent::__get() is a magic method')),
			'writable property'			=> array('WritableEvent', array('WritableEvent::$text is publicly settable')),
			'static property'			=> array('StaticEvent', array('StaticEvent::$count is publicly settable')),
			'array of columns'			=> array('ColumnsEvent', array('ColumnsEvent::row() return is an array not documented as a list of scalars or data interfaces')),
			'service'					=> array('ServiceEvent', array('ServiceEvent::journal() return is PunBBFixture\\Module\\Greeting\\Model\\Journal, not a scalar or an Api\\Data interface')),
			'service property'			=> array('ServicePropertyEvent', array('ServicePropertyEvent::$journal is PunBBFixture\\Module\\Greeting\\Model\\Journal, not a scalar or an Api\\Data interface')),
			'mixed'						=> array('MixedEvent', array('MixedEvent::payload() return is mixed, not a scalar or an Api\\Data interface')),
			'untyped'					=> array('UntypedEvent', array('UntypedEvent::reword() $text is untyped', 'UntypedEvent::reword() return is untyped')),
			'reference returned'		=> array('ReferenceEvent', array('ReferenceEvent::text() returns by reference')),
			'reference parameter'		=> array('ReferenceParameterEvent', array('ReferenceParameterEvent::fill() takes $text by reference')),
		);
	}

	/** @param list<string> $expected */
	#[DataProvider('brokenEventProvider')]
	public function testTheRulesRejectABrokenEvent(string $event, array $expected): void {
		$this->assertSame($expected, self::eventProblems(self::BROKEN.$event));
	}

	/** @return array<string, array{string, string}> */
	public static function containerEventProvider(): array {
		return array(
			'array access'	=> array('OffsetEvent', 'OffsetEvent implements ArrayAccess'),
			'iteration'		=> array('IterableEvent', 'IterableEvent implements Traversable'),
		);
	}

	#[DataProvider('containerEventProvider')]
	public function testTheRulesRejectAnEventActingAsAContainer(string $event, string $expected): void {
		$this->assertContains($expected, self::eventProblems(self::BROKEN.$event));
	}
}
