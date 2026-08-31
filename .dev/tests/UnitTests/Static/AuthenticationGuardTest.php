<?php
/**
 * What the authentication walk fixed outside the two functions that have their
 * own tests: the session id at a privilege change, and the cookie flag the
 * installer wrote.
 *
 * Both need a live forum — one needs a running session, the other needs an
 * installer that refuses to run once config.php exists — so they are pinned
 * against the source, each guard paired with the shape the line had before.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class AuthenticationGuardTest extends TestCase
{
	//
	// Logging in and logging out change what the request is allowed to do, so
	// the id of any session running across them has to be replaced. The forum's
	// own credential is the cookie, but $_SESSION carries the flash messages
	// and whatever an extension puts there.
	//
	public function testLoginAndLogoutRollTheSessionId(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'login.php');

		$this->assertSame(2, substr_count($source, 'forum_session_regenerate();'),
			'login.php must roll the session id at both privilege changes');
	}

	public function testTheHelperOnlyActsOnARunningSession(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/functions.php');
		$body = substr($source, (int) strpos($source, 'function forum_session_regenerate('));
		$body = substr($body, 0, (int) strpos($body, "\n}\n"));

		$this->assertStringContainsString('session_status() !== PHP_SESSION_ACTIVE', $body);
		$this->assertStringContainsString('session_regenerate_id(true)', $body);
	}

	//
	// Calling it without a session must be a no-op rather than a warning: most
	// requests never touch $_SESSION at all.
	//
	public function testTheHelperIsSilentWithoutASession(): void
	{
		// Another test in the suite may have left one open.
		if (session_status() === PHP_SESSION_ACTIVE)
			session_write_close();

		$this->assertSame(PHP_SESSION_NONE, session_status());

		forum_session_regenerate();

		$this->assertSame(PHP_SESSION_NONE, session_status());
	}

	//
	// The case the fix exists for. session_regenerate_id(true) needs a real
	// session, which this process does not have, so it runs out of process.
	//
	public function testARunningSessionGetsANewIdAndTheOldOneIsDestroyed(): void
	{
		$save_path = sys_get_temp_dir().'/punbb_session_'.bin2hex(random_bytes(6));
		mkdir($save_path);

		try
		{
			$output = shell_exec(escapeshellarg(PHP_BINARY).' '.
				escapeshellarg(dirname(__DIR__).'/Functions/session_regenerate_harness.php').' '.
				escapeshellarg($save_path));

			$this->assertIsString($output, 'the session harness produced no output');

			$parts = explode(' ', trim((string) $output));
			$this->assertCount(4, $parts, 'harness output: '.$output);

			$this->assertNotSame('', $parts[0], 'the harness started no session');
			$this->assertNotSame($parts[0], $parts[1], 'the session id survived the privilege change');
			$this->assertSame('0', $parts[2], 'the old session was left usable');
			$this->assertSame('kept', $parts[3], 'the session data did not survive the regeneration');
		}
		finally
		{
			array_map('unlink', glob($save_path.'/*') ?: array());
			rmdir($save_path);
		}
	}

	//
	// The flash message is the one thing the forum stores in the session. It is
	// an array of two strings and must be read back as one, never as an object
	// graph.
	//
	public function testTheFlashMessageIsUnserialisedWithoutClasses(): void
	{
		$this->assertStringContainsString("unserialize(\$_SESSION['punbb_forum_flash'], array('allowed_classes' => false))",
			(string) file_get_contents(FORUM_ROOT.'include/flash_messenger.php'));
	}

	//
	// The login cookie carries the account's password hash, so config.php must
	// not tell the forum to send it over plain HTTP on an HTTPS install. The
	// installer wrote `$cookie_secure = 0;` whatever the base URL said.
	//
	public function testTheInstallerDerivesCookieSecureFromTheBaseUrl(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'admin/install.php');
		$body = substr($source, (int) strpos($source, 'function generate_config_file('));
		$body = substr($body, 0, (int) strpos($body, "\n}\n"));

		$this->assertStringContainsString("stripos(\$base_url, 'https://') === 0", $body);
		$this->assertStringNotContainsString('$cookie_secure = 0;', $body,
			'the installer still hardcodes an insecure cookie');
	}

	//
	// The upgrade script keeps whatever config.php already says, so the flag
	// survives an upgrade rather than being reset to the old default.
	//
	public function testTheUpgradeScriptPreservesTheFlag(): void
	{
		$this->assertStringContainsString('\\$cookie_secure = $cookie_secure;',
			(string) file_get_contents(FORUM_ROOT.'admin/db_update.php'));
	}
}
