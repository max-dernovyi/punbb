<?php
/**
 * The container is composed where what it will be handed exists: in
 * include/common.php once cookie_login() has populated $forum_user, never in
 * include/essentials.php, which finishes before that.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class CompositionRootTest extends TestCase {
	private const ROOT = 'ModuleRegistry::discover(';

	private static function source(string $file): string {
		return (string) file_get_contents(FORUM_ROOT.$file);
	}

	public function testTheRootIsComposedOnceInCommonAfterTheUserIsPopulated(): void {
		$common = self::source('include/common.php');
		$user = strpos($common, 'cookie_login($forum_user);');

		$this->assertSame(1, substr_count($common, self::ROOT));
		$this->assertNotFalse($user);
		$this->assertGreaterThan($user, strpos($common, self::ROOT));
		$this->assertMatchesRegularExpression('/^\$forum_container = PunBB\\\\Module\\\\Framework\\\\Modules\\\\'.preg_quote(self::ROOT, '/').'/m', $common);
	}

	public function testEssentialsDoesNotReachTheNewCore(): void {
		$this->assertStringNotContainsString('PunBB\\', self::source('include/essentials.php'));
	}
}
