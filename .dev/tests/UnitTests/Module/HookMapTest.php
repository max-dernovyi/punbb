<?php
/**
 * The bridge's mapping table names, for a legacy hook id, the event or plugin
 * that now covers the point. It stays empty until pages move; every entry it
 * gains must name an inventoried point and something that exists to cover it.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\LegacyBridge\Hook\HookMap;

class HookMapTest extends TestCase {
	private const INVENTORY = FORUM_ROOT.'.dev/tests/fixtures/hook_points.txt';

	/** @return array<string, true> every point the inventory lists, removed ones included */
	private static function inventory(): array {
		preg_match_all('/^([A-Za-z0-9_]+)(?:\s+--.*)?$/m', (string) file_get_contents(self::INVENTORY), $matches);

		return array_fill_keys($matches[1], true);
	}

	/**
	 * @param array<string, string> $table
	 * @return list<string> what is wrong with each entry
	 */
	private static function problems(array $table): array {
		$inventory = self::inventory();
		$problems = array();

		foreach ($table as $point => $target)
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

	public function testEveryEntryMapsAnInventoriedPointToAnEventOrAContractMethod(): void {
		$problems = self::problems(HookMap::COVERED);

		$this->assertSame(array(), $problems, implode("\n", $problems));
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
		$this->assertNull((new HookMap())->coveredBy('vt_start'));
	}
}
