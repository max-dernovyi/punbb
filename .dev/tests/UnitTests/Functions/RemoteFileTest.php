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
 * opened. The live cases fetch through the real client on both of its
 * transports, from remote_server.php, with a CA of the harness's own trusted.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/RemoteHarness.php';

class RemoteFileTest extends TestCase
{
	/** The port a redirect's origin is rebuilt from follows the scheme. */
	public function testTheDefaultPortFollowsTheScheme(): void
	{
		$https = forum_remote_url_parts('https://punbb.informer.com/update/manifest/foo.xml');
		$http = forum_remote_url_parts('http://example.com/feed');

		$this->assertIsArray($https);
		$this->assertIsArray($http);
		$this->assertSame(array('https', 443), array($https['scheme'], $https['port']));
		$this->assertSame(array('http', 80), array($http['scheme'], $http['port']));
	}

	public function testTheSchemeIsReadCaseInsensitively(): void
	{
		$parts = forum_remote_url_parts('HTTPS://Example.COM/x');

		$this->assertIsArray($parts);
		$this->assertSame('https', $parts['scheme']);
		$this->assertSame(443, $parts['port']);
	}

	public function testAnExplicitPortIsHonoured(): void
	{
		$parts = forum_remote_url_parts('https://example.com:8443/x');

		$this->assertIsArray($parts);
		$this->assertSame(8443, $parts['port']);
		$this->assertSame('https', $parts['scheme']);
	}

	/**
	 * A relative Location: is resolved against this, so the query has to
	 * survive and an empty path has to become "/".
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
	 * sees one. The validated form is what goes on the wire.
	 */
	public function testTheValidatedUrlIsWhatIsFetched(): void
	{
		$parts = forum_remote_url_parts("  https://example.com/x\0 ");

		$this->assertIsArray($parts);
		$this->assertSame('https://example.com/x', $parts['url']);
		$this->assertStringNotContainsString("\0", $parts['url']);
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
	 * A CONNECT proxy answers in front of the origin. Reading the first block
	 * would look for the Location: in "200 Connection established".
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

	/** @return array<string, array{string}> */
	public static function transportProvider(): array
	{
		return array(
			'cURL'		=> array('curl'),
			'socket'	=> array('socket'),
		);
	}

	/**
	 * What get_remote_file() returned for $path on a server over $certificate
	 * ('' for cleartext), fetched as $scheme on $transport. Nothing else may
	 * have been printed.
	 */
	private function fetch(string $transport, string $certificate, string $scheme, string $path, int $timeout = 2, bool $headOnly = false): mixed
	{
		$server = RemoteHarness::server($certificate);

		try
		{
			$fetched = RemoteHarness::fetch($scheme.'://'.RemoteHarness::HOST.':'.$server['port'].$path, $transport, $timeout, $headOnly);
		}
		finally
		{
			RemoteHarness::stop($server);
		}

		$this->assertSame('', $fetched['output']);
		$this->assertTrue($fetched['fetches'], 'fetchesRemoteFiles() denies a transport the client used');

		return $fetched['result'];
	}

	/** The control for every refusal below: the harness's CA is trusted, and the server answers. */
	#[DataProvider('transportProvider')]
	public function testACertificateTheTrustedCaIssuedIsFetched(string $transport): void
	{
		$this->assertSame(
			array('headers' => array('HTTP/1.1 200 OK', 'Content-Type: text/plain', 'Content-Length: 11'), 'content' => 'remote body'),
			$this->fetch($transport, 'trusted', 'https', '/ok')
		);
	}

	/** @return array<string, array{string, string}> */
	public static function badCertificateProvider(): array
	{
		$cases = array();
		foreach (self::transportProvider() as $name => list($transport))
			foreach (array('wrong-host', 'expired', 'self-signed') as $certificate)
				$cases[$certificate.' on '.$name] = array($transport, $certificate);

		return $cases;
	}

	/** A hotfix manifest is eval()ed: whoever is on the wire must not be able to answer for the host. */
	#[DataProvider('badCertificateProvider')]
	public function testABadCertificateIsRefused(string $transport, string $certificate): void
	{
		$this->assertNull($this->fetch($transport, $certificate, 'https', '/ok'));
	}

	/**
	 * Every built-in update and hotfix URL is HTTPS, so a transport without TLS
	 * must not offer update checks, while plain HTTP still fetches.
	 */
	public function testATransportWithoutTlsDoesNotClaimToFetch(): void
	{
		$server = RemoteHarness::server();

		try
		{
			$fetched = RemoteHarness::fetch('http://'.RemoteHarness::HOST.':'.$server['port'].'/ok', 'no-tls');
		}
		finally
		{
			RemoteHarness::stop($server);
		}

		$this->assertSame('', $fetched['output']);
		$this->assertSame('remote body', $fetched['result']['content'] ?? null);
		$this->assertFalse($fetched['fetches'], 'fetchesRemoteFiles() claims HTTPS without a TLS transport');
		$this->assertFalse(RemoteHarness::fetch('https://'.RemoteHarness::HOST.':'.RemoteHarness::closedPort().'/ok', 'no-tls')['fetches']);
	}

	/**
	 * The scheme picks the transport: an https:// URL is never fetched in
	 * cleartext, even from a server that answers in cleartext what it takes
	 * for a request. The http:// fetch from the same server is the control.
	 */
	#[DataProvider('transportProvider')]
	public function testHttpsIsNeverFetchedInCleartext(string $transport): void
	{
		$this->assertNull($this->fetch($transport, '', 'https', '/ok'));
		$this->assertSame('remote body', $this->fetch($transport, '', 'http', '/ok')['content'] ?? null);
	}

	/** Each hop re-enters get_remote_file(), and so its check: the headers of every hop are kept in order. */
	#[DataProvider('transportProvider')]
	public function testARedirectIsFollowedThroughGetRemoteFile(string $transport): void
	{
		$this->assertSame(
			array(
				'headers' => array('HTTP/1.1 302 Found', 'Location: /ok', 'Content-Length: 0', 'HTTP/1.1 200 OK', 'Content-Type: text/plain', 'Content-Length: 11'),
				'content' => 'remote body',
			),
			$this->fetch($transport, 'trusted', 'https', '/moved')
		);
	}

	#[DataProvider('transportProvider')]
	public function testARedirectToAnotherSchemeIsRefused(string $transport): void
	{
		$this->assertNull($this->fetch($transport, '', 'http', '/moved-file'));
	}

	#[DataProvider('transportProvider')]
	public function testARedirectLoopEnds(string $transport): void
	{
		$this->assertNull($this->fetch($transport, '', 'http', '/moved-loop'));
	}

	#[DataProvider('transportProvider')]
	public function testAnythingButA200IsNull(string $transport): void
	{
		$this->assertNull($this->fetch($transport, '', 'http', '/missing'));
	}

	/** A server that never answers costs the caller its timeout, then a null. */
	#[DataProvider('transportProvider')]
	public function testATimeoutIsAValue(string $transport): void
	{
		$started = microtime(true);

		$this->assertNull($this->fetch($transport, '', 'http', '/silent', 1));
		$this->assertLessThan(5, microtime(true) - $started);
	}

	#[DataProvider('transportProvider')]
	public function testARefusedConnectionIsAValue(string $transport): void
	{
		$fetched = RemoteHarness::fetch('http://'.RemoteHarness::HOST.':'.RemoteHarness::closedPort().'/ok', $transport);

		$this->assertSame(array('result' => null, 'fetches' => true, 'output' => ''), $fetched);
	}

	#[DataProvider('transportProvider')]
	public function testAHeadRequestHasNoContent(string $transport): void
	{
		$this->assertSame(
			array('headers' => array('HTTP/1.1 200 OK', 'Content-Type: text/plain', 'Content-Length: 11')),
			$this->fetch($transport, '', 'http', '/ok', 2, true)
		);
	}

	/** What the forum puts on the wire: the validated URL, HTTP/1.0 and its own name. */
	#[DataProvider('transportProvider')]
	public function testTheRequestCarriesTheValidatedUrl(string $transport): void
	{
		$server = RemoteHarness::server();

		try
		{
			$fetched = RemoteHarness::fetch("  http://".RemoteHarness::HOST.':'.$server['port']."/echo\0 ", $transport);
		}
		finally
		{
			RemoteHarness::stop($server);
		}

		$this->assertSame('', $fetched['output']);
		$this->assertIsArray($fetched['result']);
		$this->assertStringStartsWith("GET /echo HTTP/1.0\r\nHost: ".RemoteHarness::HOST.':'.$server['port']."\r\nUser-Agent: PunBB\r\n", $fetched['result']['content']);
	}

	/**
	 * Without cURL the client picks its socket transport by fsockopen() alone
	 * and would call a disabled stream_socket_client(). Nothing is fetched,
	 * nothing is printed, and the installer and the settings page are told.
	 */
	public function testWithoutATransportNothingIsFetched(): void
	{
		$server = RemoteHarness::server();

		try
		{
			$fetched = RemoteHarness::fetch('http://'.RemoteHarness::HOST.':'.$server['port'].'/ok', 'none');
		}
		finally
		{
			RemoteHarness::stop($server);
		}

		$this->assertSame(array('result' => null, 'fetches' => false, 'output' => ''), $fetched);
	}

	/** The request is the library's to make; nothing in the tree speaks HTTP by hand. */
	public function testTheHandRolledTransportsAreGone(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/functions.php');
		$body = substr($source, (int) strpos($source, 'function get_remote_file('));
		$body = substr($body, 0, (int) strpos($body, "\n}\n"));

		$this->assertStringContainsString('\\WpOrg\\Requests\\Requests::request($parsed_url[\'url\']', $body);

		foreach (array('curl_init', 'curl_exec', 'stream_socket_client', 'fsockopen', 'file_get_contents', 'fwrite', 'ini_set') as $call)
			$this->assertStringNotContainsString($call.'(', $body);
	}
}
