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
	 * trim() strips a leading or trailing NUL, so the control-byte check never
	 * sees one and cURL would be handed a URL that raises a ValueError. The
	 * validated form is what goes on the wire.
	 */
	public function testTheValidatedUrlIsWhatIsFetched(): void
	{
		$parts = forum_remote_url_parts("  https://example.com/x\0 ");

		$this->assertIsArray($parts);
		$this->assertSame('https://example.com/x', $parts['url']);
		$this->assertStringNotContainsString("\0", $parts['url']);

		$source = (string) file_get_contents(FORUM_ROOT.'include/functions.php');
		$this->assertStringContainsString('curl_setopt($ch, CURLOPT_URL, $parsed_url[\'url\']);', $source);
		$this->assertStringNotContainsString('curl_setopt($ch, CURLOPT_URL, $url);', $source);
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
			'CRLF in host'      => array("https://example.com\r\nX-Injected: 1/x"),
			'CRLF in path'      => array("https://example.com/x\r\nX-Injected: 1"),
			'LF in path'        => array("https://example.com/x\nX-Injected: 1"),
			'space in path'     => array('https://example.com/x y'),
			'NUL in host'       => array("https://example.com\0.evil.test/x"),
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

	/**
	 * A redirect hop re-enters get_remote_file(), which only takes an absolute
	 * http(s) URL — but a server answers Location: with whatever RFC 7231
	 * allows, and punbb.informer.com answers the update check with a path.
	 * The wrapper used to resolve those; now this does.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function redirectProvider(): array
	{
		return array(
			'absolute'         => array('https://other.example/x.xml', 'https://other.example/x.xml'),
			'scheme relative'  => array('//other.example/x.xml', 'https://other.example/x.xml'),
			'root relative'    => array('/update/manifest/x.xml', 'https://punbb.informer.com/update/manifest/x.xml'),
			'path relative'    => array('x.xml', 'https://punbb.informer.com/update/manifest/x.xml'),
			'query only'       => array('?v=2', 'https://punbb.informer.com/update/manifest/foo.xml?v=2'),
			'fragment only'    => array('#next', 'https://punbb.informer.com/update/manifest/foo.xml?v=1#next'),
			'trimmed'          => array(" /x.xml\r", 'https://punbb.informer.com/x.xml'),
		);
	}

	#[DataProvider('redirectProvider')]
	public function testARelativeLocationIsResolvedAgainstTheFetchedUrl(string $location, string $expected): void
	{
		$parts = forum_remote_url_parts('https://punbb.informer.com/update/manifest/foo.xml?v=1');

		$this->assertIsArray($parts);
		$this->assertSame($expected, forum_remote_redirect_url($location, $parts));
	}

	public function testAPortInTheBaseSurvivesTheHop(): void
	{
		$parts = forum_remote_url_parts('http://example.com:8080/a/b');

		$this->assertIsArray($parts);
		$this->assertSame('http://example.com:8080/a/c', forum_remote_redirect_url('c', $parts));
		$this->assertSame('http://example.com:8080/c', forum_remote_redirect_url('/c', $parts));
	}

	/** @return array<string, array{mixed}> */
	public static function unusableLocationProvider(): array
	{
		return array(
			'empty'        => array(''),
			'whitespace'   => array("  \r\n"),
			'not a string' => array(null),
		);
	}

	#[DataProvider('unusableLocationProvider')]
	public function testALocationWithNothingInItIsRefused(mixed $location): void
	{
		$parts = forum_remote_url_parts('https://example.com/x');

		$this->assertIsArray($parts);
		$this->assertFalse(forum_remote_redirect_url($location, $parts));
	}

	/**
	 * The resolver hands the value on as it stands when it already carries a
	 * scheme; forum_remote_url_parts() is what refuses the scheme, and the
	 * hop has to reach it rather than being turned into a relative path.
	 */
	public function testAnotherSchemeIsLeftForTheValidatorToRefuse(): void
	{
		$parts = forum_remote_url_parts('https://example.com/x');

		$this->assertIsArray($parts);
		$this->assertSame('file:///etc/passwd', forum_remote_redirect_url('file:///etc/passwd', $parts));
		$this->assertFalse(forum_remote_url_parts('file:///etc/passwd'));
	}

	/**
	 * Every redirect branch resolves before it recurses. Without this the
	 * fopen branch (the only one that used to delegate redirects to the
	 * stream wrapper) drops a relative Location on the floor.
	 */
	public function testEveryRedirectBranchResolvesTheLocation(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/functions.php');
		$body = substr($source, (int) strpos($source, 'function get_remote_file('));

		$this->assertSame(0, substr_count($body, 'forum_remote_response_headers($content)'),
			'a branch that parses a raw response passes the header region, never the body');
		$this->assertSame(2, substr_count($body, 'forum_remote_response_headers('),
			'the branches that parse a raw response read the origin header block');
		$this->assertSame(3, substr_count($body, 'forum_remote_location_header($headers)'),
			'a redirect branch reads the Location: header itself');
		$this->assertSame(3, substr_count($body, 'forum_remote_redirect_url($location, $parsed_url)'),
			'a redirect branch recurses on the raw Location: value');
		$this->assertSame(0, substr_count($body, 'get_remote_file(substr($header, 10)'),
			'a redirect branch recurses on the raw Location: value');
	}

	/**
	 * cURL is handed the response of a CONNECT proxy in front of the origin's
	 * own, and CURLINFO_HTTP_CODE reports the origin's status. Reading the
	 * first block would look for the Location: in "200 Connection established".
	 */
	public function testTheHeadersOfTheOriginResponseAreRead(): void
	{
		$content = "HTTP/1.1 200 Connection established\r\n\r\n".
			"HTTP/1.1 302 Found\r\nLocation: /next\r\n\r\n".
			"a body that mentions HTTP/1.1 200 OK\r\n";

		$headers = forum_remote_response_headers($content);

		$this->assertSame(array('HTTP/1.1 302 Found', 'Location: /next'), $headers);
		$this->assertSame('/next', forum_remote_location_header($headers));
	}

	/**
	 * The header region ends at the first blank line the caller hands over; a
	 * body opening with "HTTP/" is not a second header block and may not
	 * replace the origin's Location:.
	 */
	public function testABodyOpeningWithAStatusLineIsNotReadAsHeaders(): void
	{
		$response = "HTTP/1.1 302 Found\r\nLocation: /next\r\n\r\nHTTP/1.1 diagnostic\r\nLocation: /evil\r\n";
		$header_end = strpos($response, "\r\n\r\n");

		$headers = forum_remote_response_headers(substr($response, 0, (int) $header_end));

		$this->assertSame(array('HTTP/1.1 302 Found', 'Location: /next'), $headers);
		$this->assertSame('/next', forum_remote_location_header($headers));
	}

	public function testASingleHeaderBlockIsReadAsItStands(): void
	{
		$this->assertSame(
			array('HTTP/1.1 302 Found', 'Location: /next'),
			forum_remote_response_headers("HTTP/1.1 302 Found\r\nLocation: /next\r\n\r\nbody")
		);
		$this->assertSame(
			array('HTTP/1.1 302 Found', 'Location: /next'),
			forum_remote_response_headers("HTTP/1.1 302 Found\r\nLocation: /next")
		);
	}

	/** @return array<string, array{string, ?string}> */
	public static function locationHeaderProvider(): array
	{
		return array(
			'canonical'      => array('Location: /next', '/next'),
			'lower case'     => array('location: /next', '/next'),
			'upper case'     => array('LOCATION: /next', '/next'),
			'no space'       => array('Location:/next', '/next'),
			'tab'            => array("Location:\t/next", '/next'),
			'another header' => array('Content-Length: 12', null),
			'a prefix only'  => array('X-Location: /next', null),
		);
	}

	/** A field name is case-insensitive and the space after the colon is optional. */
	#[DataProvider('locationHeaderProvider')]
	public function testTheLocationHeaderIsReadCaseInsensitively(string $header, ?string $expected): void
	{
		$this->assertSame($expected, forum_remote_location_header(array('HTTP/1.1 302 Found', $header)));
	}

	/** @return array<string, array{string, bool}> */
	public static function redirectStatusProvider(): array
	{
		return array(
			'301' => array('HTTP/1.1 301 Moved Permanently', true),
			'302' => array('HTTP/1.0 302 Found', true),
			'303' => array('HTTP/1.1 303 See Other', true),
			'307' => array('HTTP/1.1 307 Temporary Redirect', true),
			'308' => array('HTTP/1.1 308 Permanent Redirect', true),
			'200' => array('HTTP/1.1 200 OK', false),
			'304' => array('HTTP/1.1 304 Not Modified', false),
			'404' => array('HTTP/1.1 404 Not Found', false),
		);
	}

	/** Every 3xx that carries a Location is followed, not only 301 and 302. */
	#[DataProvider('redirectStatusProvider')]
	public function testAStatusLineIsRecognisedAsARedirect(string $status, bool $expected): void
	{
		$this->assertSame($expected, forum_remote_is_redirect($status));
	}
}
