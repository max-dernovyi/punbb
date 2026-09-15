<?php
/**
 * The bridge's mapping table names, for a legacy hook id, the event or plugin
 * that now covers the point. It fills as pages move; every entry must name an
 * inventoried point and something that exists to cover it, and a point an
 * event covers must be run by the bridge's observer of that event.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\LegacyBridge\Hook\HookMap;
use PunBB\Module\LegacyBridge\Module as LegacyBridgeModule;

class HookMapTest extends TestCase {
	private const INVENTORY = FORUM_ROOT.'.dev/tests/fixtures/hook_points.txt';

	/** @return array<string, true> every point the inventory lists, removed ones included */
	private static function inventory(): array {
		preg_match_all('/^([A-Za-z0-9_]+)(?:\s+--.*)?$/m', (string) file_get_contents(self::INVENTORY), $matches);

		return array_fill_keys($matches[1], true);
	}

	/**
	 * @param array<string, string|list<string>> $table
	 * @return list<string> what is wrong with each entry
	 */
	private static function problems(array $table): array {
		$inventory = self::inventory();
		$problems = array();

		foreach (self::entries($table) as [$point, $target])
		{
			if (!isset($inventory[$point]))
				$problems[] = $point.': not a hook point the inventory lists';

			if (preg_match('/^(\w+(?:\\\\\w+)*\\\\Api\\\\\w+)::(\w+)$/', $target, $match) === 1)
			{
				if (!interface_exists($match[1]) || !method_exists($match[1], $match[2]))
					$problems[] = $point.': '.$target.' is not a method of an Api contract';
			}
			else if (!is_a($target, EventInterface::class, true) || !(new ReflectionClass($target))->isFinal())
				$problems[] = $point.': '.$target.' is neither a final event class nor an Api contract method';
		}

		return $problems;
	}

	/**
	 * @param array<string, string|list<string>> $table
	 * @return list<array{string, string}> each point with each target covering it
	 */
	private static function entries(array $table): array {
		$entries = array();
		foreach ($table as $point => $targets)
			foreach ((array) $targets as $target)
				$entries[] = array($point, $target);

		return $entries;
	}

	public function testEveryEntryMapsAnInventoriedPointToAnEventOrAContractMethod(): void {
		$problems = self::problems(HookMap::COVERED);

		$this->assertSame(array(), $problems, implode("\n", $problems));
	}

	/** A covered point's stored code runs only where its event is observed, so the bridge must observe it there. */
	public function testEveryPointAnEventCoversIsRunByTheBridgesObserverOfThatEvent(): void {
		$wiring = new Wiring('LegacyBridge');
		(new LegacyBridgeModule())->wire($wiring);

		$observers = array();
		foreach ($wiring->observers() as $declaration)
			$observers[$declaration->event][] = $declaration->class;

		$unrun = array();
		foreach (self::entries(HookMap::COVERED) as [$point, $target])
		{
			if (!is_a($target, EventInterface::class, true))
				continue;

			$sources = '';
			foreach ($observers[$target] ?? array() as $observer)
				$sources .= file_get_contents((string) (new ReflectionClass($observer))->getFileName());

			if (!str_contains($sources, "'".$point."'"))
				$unrun[] = $point.' ('.$target.')';
		}

		$this->assertSame(array(), $unrun, 'covered points no observer of their event runs');
	}

	/** The check has to reject a wrong entry, or the empty table proves nothing. */
	public function testTheCheckRejectsWhatCoversNothing(): void {
		$this->assertSame(array(), self::problems(array(
			'vt_modify_topic_info'			=> 'PunBBFixture\\Module\\Greeting\\Event\\GreetingSending',
			'in_qr_get_cats_and_forums'		=> 'PunBBFixture\\Module\\Greeting\\Api\\GreeterInterface::greet',
		)));

		$this->assertSame(array(
			'x_not_a_point: not a hook point the inventory lists',
			'vt_start: PunBBFixture\\Module\\Greeting\\Model\\Journal is neither a final event class nor an Api contract method',
			'vt_end: PunBBFixture\\Module\\Greeting\\Api\\GreeterInterface::shout is not a method of an Api contract',
			'in_start: PunBBFixture\\Module\\Greeting\\Model\\Greeter::greet is neither a final event class nor an Api contract method',
		), self::problems(array(
			'x_not_a_point'	=> 'PunBBFixture\\Module\\Greeting\\Event\\GreetingSending',
			'vt_start'		=> 'PunBBFixture\\Module\\Greeting\\Model\\Journal',
			'vt_end'		=> 'PunBBFixture\\Module\\Greeting\\Api\\GreeterInterface::shout',
			'in_start'		=> 'PunBBFixture\\Module\\Greeting\\Model\\Greeter::greet',
		)));
	}

	public function testALookupNamesWhatCoversAPointAndNothingForTheRest(): void {
		$map = new HookMap(array('vt_start' => 'PunBBFixture\\Module\\Greeting\\Event\\GreetingSending'));

		$this->assertSame('PunBBFixture\\Module\\Greeting\\Event\\GreetingSending', $map->coveredBy('vt_start'));
		$this->assertNull($map->coveredBy('vt_end'));
		$this->assertNull((new HookMap())->coveredBy('fn_get_remote_address_start'));
	}

	/** An id a page script fired at two sites is covered at each, by what covers that site. */
	public function testAnIdFiredAtTwoSitesListsWhatCoversEach(): void {
		$map = new HookMap(array('vt_start' => array('PunBBFixture\\Module\\Greeting\\Event\\GreetingSending', 'PunBBFixture\\Module\\Greeting\\Api\\GreeterInterface::greet')));

		$this->assertSame('PunBBFixture\\Module\\Greeting\\Event\\GreetingSending', $map->coveredBy('vt_start'));
		$this->assertSame(array('PunBBFixture\\Module\\Greeting\\Event\\GreetingSending', 'PunBBFixture\\Module\\Greeting\\Api\\GreeterInterface::greet'), $map->covering('vt_start'));
		$this->assertSame(array(), $map->covering('vt_end'));
	}
}
