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
	private const ROOT = 'ModuleRegistry::forum(';

	/** The root that builds the container reports each third-party module it skips to the PHP error log. */
	private const REPORTING_ROOT = 'ModuleRegistry::forum(FORUM_ROOT, error_log(...))->container();';

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
		$this->assertStringContainsString(self::REPORTING_ROOT, $common);
	}

	/** The installer and the updater compose it in include/setup.php, which boots no board. */
	public function testASetupRouteComposesTheRootInSetupPhp(): void {
		$setup = self::source('include/setup.php');

		$this->assertSame(1, substr_count($setup, self::ROOT));
		$this->assertMatchesRegularExpression('/^\$forum_container = PunBB\\\\Module\\\\Framework\\\\Modules\\\\'.preg_quote(self::ROOT, '/').'/m', $setup);
		$this->assertStringContainsString(self::REPORTING_ROOT, $setup);
		$this->assertStringNotContainsString('include/essentials.php', $setup);
		$this->assertStringNotContainsString('include/common.php', $setup);
	}

	/** The upgrade instructions delete the installer's and the updater's modules; the board must still boot and route without them. */
	public function testTheBoardRoutesWithTheInstallerAndTheUpdaterDeleted(): void {
		$output = (string) shell_exec(escapeshellarg(PHP_BINARY).' -d display_errors=1 -d error_reporting=-1 '.escapeshellarg(__DIR__.'/deleted_setup_modules_harness.php').' 2>&1');

		$this->assertSame('bridge index no updater no installer events', $output);
	}

	public function testEssentialsDoesNotReachTheNewCore(): void {
		$this->assertStringNotContainsString('PunBB\\', self::source('include/essentials.php'));
	}
}
