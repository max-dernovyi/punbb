<?php
/**
 * The static-analysis gate must not be silently weakened.
 *
 * Level 2 is what this migration series analyses at: it adds undefined
 * variables and unknown methods on top of level 0. Lowering the level, or
 * dropping the baseline, would hide exactly the class of PHP 8 breakage the
 * deprecation work is chasing.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class PhpstanLevelTest extends TestCase {
	private const MIN_LEVEL = 2;

	private function config(): string {
		$path = FORUM_ROOT.'phpstan.neon';
		$this->assertFileExists($path);
		return file_get_contents($path);
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
}
