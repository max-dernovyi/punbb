<?php
/**
 * get_hook() hands a call site the code attached to its point, or false.
 *
 * Every site is `($hook = get_hook('x')) ? eval($hook) : null;`, so false means
 * nothing runs, and a `$return` site reads null. FORUM_DISABLE_HOOKS turns
 * every point off, including one with code attached.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class HookDispatchTest extends TestCase {
	private const KNOWN = 'fn_get_remote_address_start';

	/** @var array<string, array<string, string|false>> */
	private static array $returned = array();

	/** One body on every inventoried point, two on KNOWN. */
	private static function hooks(): array {
		preg_match_all('/^([a-z0-9_]+)$/m', (string) file_get_contents(FORUM_ROOT.'.dev/tests/fixtures/hook_points.txt'), $matches);

		$hooks = array_fill_keys($matches[1], array('return \'attached\';'));
		$hooks[self::KNOWN] = array('$first = 1;', '$second = 2;');

		return $hooks;
	}

	/** @return array<string, string|false> what get_hook() returned per id */
	private function dispatch(string $state): array {
		if (!isset(self::$returned[$state]))
		{
			$file = tempnam(sys_get_temp_dir(), 'punbb_hooks_');
			file_put_contents($file, json_encode(self::hooks()));

			$output = (string) shell_exec(escapeshellarg(PHP_BINARY).' -d display_errors=1 -d error_reporting=-1 '.
				escapeshellarg(__DIR__.'/hook_dispatch_harness.php').' '.escapeshellarg($state).' '.escapeshellarg($file).' 2>&1');
			unlink($file);

			$returned = json_decode($output, true);
			$this->assertIsArray($returned, 'the harness did not report: '.$output);

			self::$returned[$state] = $returned;
		}

		return self::$returned[$state];
	}

	public function testAnUnknownPointReturnsFalse(): void {
		$this->assertFalse($this->dispatch('enabled')['punbb_fixture_unknown_point']);
	}

	public function testAKnownPointReturnsItsBodiesJoinedInOrder(): void {
		$this->assertSame("\$first = 1;\n\$second = 2;", $this->dispatch('enabled')[self::KNOWN]);
	}

	public function testEveryInventoriedPointReturnsWhatIsAttached(): void {
		$returned = $this->dispatch('enabled');
		unset($returned[self::KNOWN], $returned['punbb_fixture_unknown_point']);

		$this->assertGreaterThan(0, count($returned));
		$this->assertSame(array('return \'attached\';'), array_values(array_unique($returned)));
	}

	public function testDisabledHooksReturnFalseForEveryPoint(): void {
		$returned = $this->dispatch('disabled');

		$this->assertCount(count(self::hooks()) + 1, $returned);
		$this->assertSame(array(false), array_values(array_unique($returned, SORT_REGULAR)));
	}
}
