<?php
/**
 * What get_remote_file() is allowed to connect to.
 *
 * The forum fetches the update feed, the extension repositories and — through
 * `admin/extensions.php?install_hotfix=` — a manifest whose <hook> content is
 * stored in `extension_hooks` and eval()ed on every page load afterwards. The
 * transport therefore decides whether an administrator clicking "install
 * hotfix" runs code from punbb.informer.com or code from whoever is on the
 * wire, so the URL is split by forum_remote_url_parts() before anything is
 * opened.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RemoteFileTest extends TestCase
{
	/**
	 * The socket branch used to call fsockopen($host, $port ?: 80) whatever
	 * the scheme said, so every https:// caller was fetched in cleartext on
	 * port 80 whenever cURL was absent. The pre-fix port is the control.
	 */
	public function testHttpsIsNeverFetchedInCleartext(): void
	{
		$parts = forum_remote_url_parts('https://punbb.informer.com/update/manifest/foo.xml');

		$this->assertIsArray($parts);
		$this->assertSame('ssl', $parts['transport']);
		$this->assertSame(443, $parts['port']);
		$this->assertNotSame(80, $parts['port'], 'an https:// URL is back on the cleartext port');
	}

	public function testHttpKeepsThePlainTransport(): void
	{
		$parts = forum_remote_url_parts('http://example.com/feed');

		$this->assertIsArray($parts);
		$this->assertSame('tcp', $parts['transport']);
		$this->assertSame(80, $parts['port']);
	}

	public function testTheSchemeIsReadCaseInsensitively(): void
	{
		$parts = forum_remote_url_parts('HTTPS://Example.COM/x');

		$this->assertIsArray($parts);
		$this->assertSame('ssl', $parts['transport']);
		$this->assertSame(443, $parts['port']);
	}

	public function testAnExplicitPortIsHonoured(): void
	{
		$parts = forum_remote_url_parts('https://example.com:8443/x');

		$this->assertIsArray($parts);
		$this->assertSame(8443, $parts['port']);
		$this->assertSame('ssl', $parts['transport']);
	}

	/**
	 * The request line is built from this, so the query has to survive and an
	 * empty path has to become "/".
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function pathProvider(): array
	{
		return array(
			'path and query' => array('https://example.com/update/?type=xml&version=1', '/update/?type=xml&version=1'),
			'path only'      => array('https://example.com/a/b.xml', '/a/b.xml'),
			'no path'        => array('https://example.com', '/'),
			'root'           => array('https://example.com/', '/'),
			'query only'     => array('https://example.com?x=1', '/?x=1'),
		);
	}

	#[DataProvider('pathProvider')]
	public function testTheRequestPathIsAssembled(string $url, string $expected): void
	{
		$parts = forum_remote_url_parts($url);

		$this->assertIsArray($parts);
		$this->assertSame($expected, $parts['path']);
	}

	/**
	 * A redirect re-enters get_remote_file(), so the Location: header of a
	 * 301 is checked by exactly this function. None of these may be opened.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function refusedProvider(): array
	{
		return array(
			'file wrapper'      => array('file:///etc/passwd'),
			'gopher'            => array('gopher://127.0.0.1:11211/_x'),
			'dict'              => array('dict://127.0.0.1:11211/stat'),
			'ftp'               => array('ftp://example.com/x'),
			'php wrapper'       => array('php://filter/read=convert.base64-encode/resource=config.php'),
			'data'              => array('data://text/plain;base64,PD9waHA='),
			'javascript'        => array('javascript:alert(1)'),
			'no scheme'         => array('//example.com/x'),
			'relative'          => array('/update/manifest.xml'),
			'no host'           => array('https:///x'),
			'empty'             => array(''),
			'not a string'      => array(null),
			'array'             => array(array('https://example.com/')),
			'port out of range' => array('https://example.com:99999/x'),
			'port zero'         => array('https://example.com:0/x'),
		);
	}

	#[DataProvider('refusedProvider')]
	public function testAnUnfetchableUrlIsRefused(mixed $url): void
	{
		$this->assertFalse(forum_remote_url_parts($url));
	}

	/** Every refusal above has to stop get_remote_file() before it opens anything. */
	#[DataProvider('refusedProvider')]
	public function testGetRemoteFileRefusesTheSameUrls(mixed $url): void
	{
		$this->assertNull(get_remote_file($url, 1));
	}

	/**
	 * Source guards: the transport must follow the scheme, and the certificate
	 * must be verified. Each carries the pre-fix line as its control.
	 */
	public function testTheSocketBranchFollowsTheSchemeAndVerifiesThePeer(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/functions.php');

		$this->assertStringNotContainsString(
			'@fsockopen($parsed_url[\'host\'], !empty($parsed_url[\'port\']) ? intval($parsed_url[\'port\']) : 80',
			$source,
			'get_remote_file() is back to connecting on port 80 whatever the scheme says'
		);
		$this->assertStringContainsString('$parsed_url[\'transport\'].\'://\'', $source);
		$this->assertStringContainsString('\'verify_peer\'', $source);
		$this->assertStringContainsString('\'verify_peer_name\'', $source);
		$this->assertStringContainsString('\'allow_self_signed\'	=> false', $source);
	}

	/** The scheme check has to sit at the entry, because redirects recurse through it. */
	public function testGetRemoteFileValidatesTheUrlBeforeAnyTransport(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/functions.php');
		$body = substr($source, (int) strpos($source, 'function get_remote_file('));

		$this->assertMatchesRegularExpression(
			'/\{\s*\$result = null;\s*\$parsed_url = forum_remote_url_parts\(\$url\);\s*if \(\$parsed_url === false\)\s*return null;/',
			$body,
			'get_remote_file() no longer refuses an unfetchable URL up front'
		);
	}
}
