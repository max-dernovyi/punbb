<?php
/**
 * Contract for forum_base_origin() and get_current_url().
 *
 * Self-referential URLs carry the origin of $base_url and the path of the
 * request. The request headers must never reach the result: CSRF tokens are
 * keyed on get_current_url(), and its value is stored as prev_url and echoed
 * back as the redirect_url hidden field.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CurrentUrlTest extends TestCase
{
	/** @var array<string, mixed> */
	private array $server;

	private string $base_url;

	/** @var array<string, mixed> */
	private array $forum_user;

	protected function setUp(): void
	{
		global $base_url, $forum_user;

		$this->server = $_SERVER;
		$this->base_url = $base_url;
		$this->forum_user = $forum_user;

		$forum_user['csrf_token'] = 'csrf-token-fixture';
	}

	protected function tearDown(): void
	{
		global $base_url, $forum_user;

		$_SERVER = $this->server;
		$base_url = $this->base_url;
		$forum_user = $this->forum_user;
	}

	/**
	 * Puts the request in the state the web server would leave it in.
	 *
	 * @param array<string, string> $server
	 */
	private function request(string $configured_base_url, array $server): void
	{
		global $base_url;

		$base_url = $configured_base_url;

		unset($_SERVER['HTTPS'], $_SERVER['HTTP_HOST'], $_SERVER['SERVER_PORT'], $_SERVER['SERVER_NAME'], $_SERVER['REQUEST_URI']);

		foreach ($server as $key => $value)
			$_SERVER[$key] = $value;
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function originProvider(): array
	{
		return array(
			'plain host'        => array('http://example.com', 'http://example.com'),
			'host with path'    => array('http://example.com/forums', 'http://example.com'),
			'https'             => array('https://example.com', 'https://example.com'),
			'explicit port'     => array('https://example.com:8443/forums', 'https://example.com:8443'),
			'default port kept' => array('http://example.com:80', 'http://example.com:80'),
			'trailing slash'    => array('http://example.com/', 'http://example.com'),
			'idn host'          => array('http://xn--e1afmkfd.xn--p1ai/f', 'http://xn--e1afmkfd.xn--p1ai'),
			// A $base_url without a scheme is not what the installer writes, but it
			// parses as a bare path - re-read as an authority so the host survives.
			'no scheme'         => array('example.com/forums', 'http://example.com'),
			'no scheme no path' => array('example.com', 'http://example.com'),
			// Nothing resembling a host: the caller has to fall back to a relative URL.
			'empty'             => array('', ''),
			'path only'         => array('/forums', ''),
		);
	}

	#[DataProvider('originProvider')]
	public function testOriginComesFromBaseUrl(string $configured, string $expected): void
	{
		$this->request($configured, array());

		$this->assertSame($expected, forum_base_origin());
	}

	public function testUnsetBaseUrlHasNoOrigin(): void
	{
		$this->request('http://example.com', array());
		unset($GLOBALS['base_url']);

		$this->assertSame('', forum_base_origin());
	}

	public function testPlainRequest(): void
	{
		$this->request('http://example.com', array(
			'HTTP_HOST'   => 'example.com',
			'SERVER_PORT' => '80',
			'REQUEST_URI' => '/viewtopic.php?id=1&p=2',
		));

		$this->assertSame('http://example.com/viewtopic.php?id=1&p=2', get_current_url());
	}

	public function testHostHeaderThatDisagreesWithBaseUrlIsIgnored(): void
	{
		$this->request('https://forum.example', array(
			'HTTP_HOST'   => 'attacker.example',
			'SERVER_PORT' => '80',
			'REQUEST_URI' => '/index.php',
		));

		$this->assertSame('https://forum.example/index.php', get_current_url());
	}

	public function testProxyRewrittenHostAndTerminatedTlsAreIgnored(): void
	{
		// What the forum saw behind the gateway: the container address in Host,
		// port 80 and no HTTPS, because the proxy terminates TLS.
		$this->request('https://punbb.loc', array(
			'HTTP_HOST'   => '172.18.0.4',
			'SERVER_PORT' => '80',
			'REQUEST_URI' => '/admin/extensions.php?section=install',
		));

		$this->assertSame('https://punbb.loc/admin/extensions.php?section=install', get_current_url());
	}

	public function testPortComesFromBaseUrlNotFromTheRequest(): void
	{
		$this->request('http://example.com:8080/forums', array(
			'HTTP_HOST'   => 'example.com',
			'SERVER_PORT' => '9999',
			'REQUEST_URI' => '/forums/index.php',
		));

		$this->assertSame('http://example.com:8080/forums/index.php', get_current_url());
	}

	public function testSubdirectoryInstallKeepsTheFullRequestPath(): void
	{
		$this->request('http://example.com/forums', array(
			'REQUEST_URI' => '/forums/misc.php?action=rules',
		));

		$this->assertSame('http://example.com/forums/misc.php?action=rules', get_current_url());
	}

	public function testMissingRequestUriYieldsTheBareOrigin(): void
	{
		$this->request('http://example.com', array());

		$this->assertSame('http://example.com', get_current_url());
	}

	public function testUnusableBaseUrlDegradesToARelativeUrl(): void
	{
		$this->request('', array(
			'HTTP_HOST'   => 'attacker.example',
			'REQUEST_URI' => '/index.php',
		));

		$this->assertSame('/index.php', get_current_url());
	}

	public function testMaxLengthReturnsNullWhenTheUrlIsTooLong(): void
	{
		$this->request('http://example.com', array(
			'REQUEST_URI' => '/viewtopic.php?id='.str_repeat('9', 255),
		));

		$this->assertNull(get_current_url(255));
	}

	public function testMaxLengthAcceptsAUrlOfExactlyThatLength(): void
	{
		$this->request('http://example.com', array(
			'REQUEST_URI' => '/x',
		));

		$url = get_current_url();
		$this->assertSame($url, get_current_url(strlen($url)));
		$this->assertNull(get_current_url(strlen($url) - 1));
	}

	public function testZeroMaxLengthNeverTruncates(): void
	{
		$this->request('http://example.com', array(
			'REQUEST_URI' => '/viewtopic.php?id='.str_repeat('9', 4096),
		));

		$this->assertIsString(get_current_url(0));
	}

	public function testForgedHostCannotReachTheRedirectUrlField(): void
	{
		global $forum_user;

		$this->request('https://forum.example', array(
			'HTTP_HOST'   => 'attacker.example',
			'SERVER_PORT' => '80',
			'REQUEST_URI' => '/login.php?action=in',
		));

		// How prev_url is filled (include/functions.php) and spent again as the
		// redirect_url hidden field (login.php, misc.php).
		$forum_user['prev_url'] = get_current_url(255);
		$field = '<input type="hidden" name="redirect_url" value="'.forum_htmlencode($forum_user['prev_url']).'" />';

		$this->assertStringNotContainsString('attacker.example', $field);
		$this->assertStringContainsString('value="https://forum.example/login.php?action=in"', $field);
	}

	public function testTokenSurvivesAHostHeaderThatChangesBetweenRenderAndSubmit(): void
	{
		$this->request('https://forum.example', array(
			'HTTP_HOST'   => 'forum.example',
			'REQUEST_URI' => '/post.php?tid=1',
		));
		$rendered = generate_form_token(get_current_url());

		// Same request, reaching PHP through a proxy that rewrote Host.
		$this->request('https://forum.example', array(
			'HTTP_HOST'   => '172.18.0.4:80',
			'SERVER_PORT' => '80',
			'REQUEST_URI' => '/post.php?tid=1',
		));

		$this->assertSame($rendered, generate_form_token(get_current_url()));
	}
}
