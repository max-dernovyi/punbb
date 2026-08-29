<?php
/**
 * forum_setcookie() after the PHP 5.2 fallback was removed.
 *
 * The old code appended '; HttpOnly' to the cookie path on PHP < 5.2, a hack
 * that produces a broken path attribute on any modern PHP. What survives is
 * the seven-argument setcookie() call, and this test pins its wire output.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class ForumSetcookieTest extends TestCase {
	/** @var resource|null */
	private static $server = null;
	private static string $base = '';

	public static function setUpBeforeClass(): void {
		$port = self::freePort();
		self::$base = 'http://127.0.0.1:'.$port.'/setcookie_harness.php';

		// display_errors off: a compile-time warning in functions.php would
		// otherwise reach the socket before the headers do.
		$command = escapeshellarg(PHP_BINARY).' -d display_errors=0 -S 127.0.0.1:'.$port.' -t '.escapeshellarg(__DIR__);
		$pipes = array();
		self::$server = proc_open($command, array(1 => array('file', '/dev/null', 'w'), 2 => array('file', '/dev/null', 'w')), $pipes);

		if (!is_resource(self::$server))
			self::markTestSkipped('could not start the built-in web server');

		self::waitForServer($port);
	}

	public static function tearDownAfterClass(): void {
		if (is_resource(self::$server))
		{
			proc_terminate(self::$server);
			proc_close(self::$server);
		}

		self::$server = null;
	}

	/** Asks the OS for a port, then releases it for the server to bind. */
	private static function freePort(): int {
		$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		$name = stream_socket_get_name($socket, false);
		fclose($socket);

		return (int)substr($name, strrpos($name, ':') + 1);
	}

	private static function waitForServer(int $port): void {
		for ($i = 0; $i < 100; ++$i)
		{
			$probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
			if ($probe !== false)
			{
				fclose($probe);
				return;
			}

			usleep(50000);
		}

		self::markTestSkipped('the built-in web server did not come up');
	}

	/** @return list<string> the Set-Cookie headers of one harness request */
	private function cookies(array $query = array()): array {
		$url = self::$base.($query ? '?'.http_build_query($query) : '');
		$body = @file_get_contents($url);

		$this->assertNotFalse($body, 'no response from '.$url);

		$headers = json_decode($body, true);
		$this->assertIsArray($headers, 'harness did not return a header list: '.$body);

		$cookies = array();
		foreach ($headers as $header)
			if (stripos($header, 'Set-Cookie:') === 0)
				$cookies[] = trim(substr($header, strlen('Set-Cookie:')));

		return $cookies;
	}

	private function cookie(array $query = array()): string {
		$cookies = $this->cookies($query);
		$this->assertCount(1, $cookies, 'expected exactly one Set-Cookie header');

		return $cookies[0];
	}

	public function testCookieCarriesNameValueAndExpiry(): void {
		$cookie = $this->cookie();

		$this->assertStringStartsWith('forum_cookie_test=', $cookie);
		$this->assertStringContainsString('a%20value', $cookie);
		$this->assertStringContainsString('expires=', $cookie);
	}

	public function testHttpOnlyIsItsOwnAttributeNotPartOfThePath(): void {
		$cookie = $this->cookie(array('path' => '/forum/'));

		$this->assertStringContainsString('; path=/forum/', $cookie);
		$this->assertStringContainsString('; HttpOnly', $cookie);
		$this->assertStringNotContainsString('path=/forum/; HttpOnly;', $cookie, 'HttpOnly must not be glued onto the path');
	}

	public function testDomainAndSecureAreForwarded(): void {
		$cookie = $this->cookie(array('domain' => 'example.com', 'secure' => 1));

		$this->assertStringContainsString('; domain=example.com', $cookie);
		$this->assertStringContainsString('; secure', $cookie);
	}

	public function testEmptyDomainAndInsecureAreOmitted(): void {
		$cookie = $this->cookie();

		$this->assertStringNotContainsString('domain=', $cookie);
		$this->assertStringNotContainsString('secure', $cookie);
	}

	public function testThePhp51PathHackIsGone(): void {
		$this->assertStringNotContainsString(
			'\'; HttpOnly\'',
			file_get_contents(FORUM_ROOT.'include/functions.php'),
			'the PHP < 5.2 HttpOnly-in-the-path fallback must be gone'
		);
	}
}
