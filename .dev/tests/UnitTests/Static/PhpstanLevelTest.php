<?php
/**
 * The static-analysis gate must not be silently weakened.
 *
 * Level 2 is what this migration series analyses at: it adds undefined
 * variables and unknown methods on top of level 0. Lowering the level, or
 * dropping the baseline, would hide exactly the class of PHP 8 breakage the
 * deprecation work is chasing.
 *
 * The new core under include/PunBB/ has a config of its own, at the highest
 * level and with no baseline; the legacy config leaves that tree to it.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class PhpstanLevelTest extends TestCase {
	private const MIN_LEVEL = 2;

	private const CORE_CONFIG = 'phpstan-core.neon';

	private const CORE_TREE = 'include/PunBB';

	private function config(string $file = 'phpstan.neon'): string {
		$path = FORUM_ROOT.$file;
		$this->assertFileExists($path);
		return file_get_contents($path);
	}

	/** @return list<string> the entries of the list under $key in $neon */
	private static function neonList(string $neon, string $key): array {
		if (preg_match('/^(\s*)'.preg_quote($key, '/').':\s*\n((?:\1\s+-\s*.+\n)+)/m', $neon, $match) !== 1)
			return array();

		preg_match_all('/-\s*(.+?)\s*$/m', $match[2], $entries);

		return $entries[1];
	}

	public function testTheConfiguredLevelIsAtLeastTwo(): void {
		$this->assertSame(1, preg_match('/^\s*level:\s*(\d+)\s*$/m', $this->config(), $match),
			'phpstan.neon declares no level');

		$this->assertGreaterThanOrEqual(self::MIN_LEVEL, (int) $match[1],
			'phpstan.neon must analyse at level '.self::MIN_LEVEL.' or higher');
	}

	public function testTheBaselineIsIncludedAndPresent(): void {
		$this->assertStringContainsString('phpstan-baseline.neon', $this->config());
		$this->assertFileExists(FORUM_ROOT.'phpstan-baseline.neon');
	}

	/**
	 * The blanket variable.undefined rule covers globals defined by an include
	 * and nothing else; a bare identifier rule would hide every local one too.
	 */
	public function testTheGlobalScopeIgnoreIsMessageScoped(): void {
		$config = $this->config();

		$this->assertStringContainsString('identifier: variable.undefined', $config);
		$this->assertSame(1, preg_match('/identifier: variable\.undefined\s*\n\s*message:/', $config),
			'the variable.undefined ignore must be narrowed by a message pattern');
	}

	public function testTheCoreIsAnalysedAtTheHighestLevelWithNoBaseline(): void {
		$config = $this->config(self::CORE_CONFIG);

		$this->assertSame(1, preg_match('/^\s*level:\s*max\s*$/m', $config), self::CORE_CONFIG.' must analyse at level max');
		$this->assertSame(array(self::CORE_TREE), self::neonList($config, 'paths'));

		$settings = (string) preg_replace('/^\s*#.*$/m', '', $config);
		foreach (array('includes:', 'baseline', 'ignoreErrors', 'excludePaths') as $weakening)
			$this->assertStringNotContainsString($weakening, $settings, self::CORE_CONFIG.' must not carry '.$weakening);
	}

	public function testTheLegacyConfigAndBaselineLeaveTheCoreToItsOwnConfig(): void {
		$this->assertContains(self::CORE_TREE, self::neonList($this->config(), 'analyse'));
		$this->assertStringNotContainsString(self::CORE_TREE, $this->config('phpstan-baseline.neon'));
	}

	public function testTheStanScriptRunsBothConfigs(): void {
		$stan = (array) json_decode($this->config('composer.json'), true)['scripts']['stan'];

		$this->assertCount(2, $stan);
		$this->assertStringNotContainsString('--configuration', $stan[0]);
		$this->assertStringEndsWith('--configuration='.self::CORE_CONFIG, $stan[1]);
	}
}
