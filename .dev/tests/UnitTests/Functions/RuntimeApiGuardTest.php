<?php
/**
 * Regression guard for the runtime APIs plan 03 task 7 removed or verified.
 *
 * Each case is a grep that must stay empty, or a shape the source must keep:
 * once fixed, nothing warns about these again, so only a scan catches a
 * reintroduction.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RuntimeApiGuardTest extends TestCase {
	/** @return list<string> every PHP file the forum serves, minus the vendored libraries */
	private static function sources(): array {
		$files = array_merge(
			(array)glob(FORUM_ROOT.'*.php'),
			(array)glob(FORUM_ROOT.'admin/*.php'),
			(array)glob(FORUM_ROOT.'include/*.php'),
			(array)glob(FORUM_ROOT.'include/dblayer/*.php')
		);

		return array_values(array_filter($files, 'is_string'));
	}

	public static function bannedApis(): array {
		return array(
			'E_STRICT'					=> array('/\bE_STRICT\b/'),
			'each()'					=> array('/(?<![\w>$])each\s*\(/'),
			'strftime()'				=> array('/(?<![\w>$])(?:gm)?strftime\s*\(/'),
			'FILTER_SANITIZE_STRING'	=> array('/\bFILTER_SANITIZE_STRING\b/'),
			'${var} interpolation'		=> array('/\$\{\s*[A-Za-z_]/'),
			'register_globals'			=> array('/\bforum_unregister_globals\b|\bregister_globals\b/'),
			// Deprecated in 8.5, both with a replacement that already works on 8.4.
			'$http_response_header'		=> array('/\$http_response_header\b/'),
			'xml_parser_free()'			=> array('/(?<![\w>$])xml_parser_free\s*\(/'),
		);
	}

	#[DataProvider('bannedApis')]
	public function testTheApiIsAbsentFromEveryServedFile(string $pattern): void {
		$offenders = array();

		foreach (self::sources() as $file)
			if (preg_match($pattern, (string)file_get_contents($file)))
				$offenders[] = substr($file, strlen(FORUM_ROOT));

		$this->assertSame(array(), $offenders, implode(', ', $offenders));
	}

	public function testTheGuardActuallyMatchesWhatItLooksFor(): void {
		foreach (self::bannedApis() as $case)
			$this->assertIsString($case[0]);

		$this->assertSame(1, preg_match('/\bE_STRICT\b/', 'error_reporting(E_ALL & ~E_STRICT);'));
		$this->assertSame(1, preg_match('/(?<![\w>$])each\s*\(/', 'while (list($k, $v) = each($a))'));
		$this->assertSame(0, preg_match('/(?<![\w>$])each\s*\(/', 'foreach ($a as $v)'));
		$this->assertSame(1, preg_match('/\$\{\s*[A-Za-z_]/', 'echo "${name}";'));
		$this->assertSame(1, preg_match('/\$http_response_header\b/', '$h = $http_response_header;'));
		$this->assertSame(1, preg_match('/(?<![\w>$])xml_parser_free\s*\(/', 'xml_parser_free($p);'));
		$this->assertSame(0, preg_match('/(?<![\w>$])xml_parser_free\s*\(/', '$this->xml_parser_free($p);'));
	}

	//
	// get_remote_file()'s allow_url_fopen branch has to report the same shape
	// as the curl and fsockopen branches above it: a list of header lines.
	//
	public function testRemoteHeadersComeFromTheAccessorAndAlwaysHaveAList(): void {
		$source = (string)file_get_contents(FORUM_ROOT.'include/functions.php');

		$this->assertTrue(function_exists('http_get_last_response_headers'), 'added in PHP 8.4, the floor of this fork');
		$this->assertStringContainsString(
			"\$result['headers'] = http_get_last_response_headers() ?? array();",
			$source,
			'the accessor returns null until a stream wrapper request has run'
		);
	}

	public function testGetRemoteFileDoesNotAssumeCurlInitSucceeded(): void {
		$source = (string)file_get_contents(FORUM_ROOT.'include/functions.php');

		$this->assertMatchesRegularExpression(
			'/\$ch = curl_init\(\);\s*\n\s*if \(\$ch === false\)/',
			$source,
			'curl_init() returns CurlHandle|false; curl_setopt(false, ...) is a TypeError'
		);
		$this->assertStringNotContainsString('is_resource($ch)', $source);
	}

	public function testGetRemoteFileChecksTheStatusLineMatchBeforeUsingIt(): void {
		$source = (string)file_get_contents(FORUM_ROOT.'include/functions.php');

		$this->assertStringNotContainsString(
			"preg_match('#HTTP/1.[01] 200 OK#', \$content, \$match, PREG_OFFSET_CAPTURE);\n",
			$source,
			'an unmatched status line leaves $match empty and $match[0][1] warns'
		);
	}

	public function testSmtpPortIsAnIntBeforeItReachesFsockopen(): void {
		$source = (string)file_get_contents(FORUM_ROOT.'include/email.php');

		$this->assertStringContainsString('$smtp_port = (int) $smtp_port;', $source);
		$this->assertMatchesRegularExpression('/fsockopen\(\$smtp_host, \$smtp_port,/', $source);
	}

	public function testSessionCookieParamsGoThroughTheOptionsArrayApi(): void {
		$source = (string)file_get_contents(FORUM_ROOT.'include/functions.php');

		$this->assertMatchesRegularExpression('/session_set_cookie_params\(array\(/', $source);
		$this->assertMatchesRegularExpression('/session_set_cookie_params\(array\((?:[^)]|\)[^;])*\'httponly\'\s*=>\s*true/s', $source);
	}

	public function testTheSessionCookieDoesNotSetSameSite(): void {
		$source = (string)file_get_contents(FORUM_ROOT.'include/functions.php');

		$this->assertStringNotContainsString("'samesite'", $source, 'plan 02 decided not to set the attribute');
	}

	public function testEssentialsNoLongerReversesRegisterGlobals(): void {
		$this->assertFalse(function_exists('forum_unregister_globals'));
	}
}
