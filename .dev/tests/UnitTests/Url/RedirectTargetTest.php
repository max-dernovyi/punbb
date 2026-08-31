<?php
/**
 * redirect() never sends the browser off this forum.
 *
 * Four callers hand it a destination taken straight from the request —
 * `redirect_url` in login.php and misc.php, `prev_url` on the CSRF confirm
 * form's cancel button — so an open redirect here is a phishing hop carrying
 * the forum's own hostname. The normalisation is only readable from inside
 * redirect(), which ends the request with a rendered page, so it is driven
 * through a harness.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RedirectTargetTest extends TestCase
{
	private const BASE = 'http://localhost';

	private function destination(string $requested): string
	{
		$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/redirect_harness.php').
			' '.escapeshellarg(self::BASE).' '.escapeshellarg($requested);

		$output = shell_exec($command);

		$this->assertIsString($output, 'the redirect harness produced no output');

		return (string) $output;
	}

	/**
	 * Every one of these is a destination a request can carry. None of them
	 * may leave the forum, and the ones that cannot be honoured land on the
	 * board index.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function hostileProvider(): array
	{
		return array(
			'foreign host'          => array('https://evil.com/x', self::BASE.'/'),
			'protocol relative'     => array('//evil.com/', self::BASE.'/'),
			// Browsers fold a backslash to a slash in the authority.
			'backslash authority'   => array('/\\evil.com', self::BASE.'/'),
			'double backslash'      => array('/\\\\evil.com', self::BASE.'/'),
			// A control character is dropped before the URL is parsed, so the
			// test has to run on what is left, not on what was sent.
			'encoded carriage return' => array('/%0d/evil.com', self::BASE.'/'),
			'encoded newline'       => array('/%0a/evil.com', self::BASE.'/'),
			// parse_url() reads everything before the @ as userinfo.
			'userinfo host'         => array('https://localhost@evil.com/', self::BASE.'/'),
			'backslash userinfo'    => array('https://evil.com\\@localhost/', self::BASE.'/'),
			'unparsable'            => array('http://:80', self::BASE.'/'),
		);
	}

	#[DataProvider('hostileProvider')]
	public function testAHostileDestinationLandsOnTheForum(string $requested, string $expected): void
	{
		$this->assertSame($expected, $this->destination($requested));
	}

	/**
	 * A scheme the prefix test does not recognise is not a way out: it is
	 * treated as a relative path and prefixed with the forum's own base URL.
	 *
	 * @return array<string, array{string}>
	 */
	public static function relativeProvider(): array
	{
		return array(
			'uppercase scheme' => array('HTTP://EVIL.COM'),
			'javascript'       => array('javascript:alert(1)'),
			'data'             => array('data:text/html,x'),
			'scheme relative'  => array('https:/\\evil.com'),
		);
	}

	#[DataProvider('relativeProvider')]
	public function testAnUnrecognisedSchemeStaysOnTheForum(string $requested): void
	{
		$this->assertStringStartsWith(self::BASE.'/', $this->destination($requested));
	}

	/**
	 * The forum still has to be able to redirect to itself, including on an
	 * install served on a scheme or port its $base_url does not name.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function honouredProvider(): array
	{
		return array(
			'relative'          => array('index.php', self::BASE.'/index.php'),
			'absolute path'     => array('/forums/index.php', '/forums/index.php'),
			'same host'         => array('http://localhost/viewtopic.php?id=1', 'http://localhost/viewtopic.php?id=1'),
			'same host on tls'  => array('https://localhost/viewtopic.php?id=1', 'https://localhost/viewtopic.php?id=1'),
			'host case'         => array('https://LOCALHOST/x', 'https://LOCALHOST/x'),
			// A backslash after the ? is payload, not authority.
			'backslash in query'=> array('index.php?a=\\b', self::BASE.'/index.php?a=\\b'),
		);
	}

	#[DataProvider('honouredProvider')]
	public function testTheForumCanStillRedirectToItself(string $requested, string $expected): void
	{
		$this->assertSame($expected, $this->destination($requested));
	}

	/** A newline in the destination would be a second header. */
	public function testTheDestinationCarriesNoControlCharacters(): void
	{
		$destination = $this->destination("index.php\r\nSet-Cookie: session=1");

		$this->assertStringStartsWith(self::BASE.'/', $destination);
		$this->assertSame(0, preg_match('/[\x00-\x1f\x7f]/', $destination),
			'the destination would break out of the Location header');
	}
}
