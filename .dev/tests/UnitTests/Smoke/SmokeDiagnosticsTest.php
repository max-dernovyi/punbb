<?php
/**
 * Covers the response parsing behind `make smoke` (.dev/bin/smoke.php): the sweep
 * is only as good as its ability to spot a PHP diagnostic in a rendered page and
 * to tell a fatal apart from a deprecation.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class SmokeDiagnosticsTest extends TestCase {
	public static function setUpBeforeClass(): void {
		require_once dirname(__DIR__, 3).'/bin/smoke.php';
	}

	public function testACleanPageHasNoDiagnostics(): void {
		$this->assertSame(array(), smoke_diagnostics('<html><body><p>Welcome</p></body></html>'));
	}

	public function testItReadsThePhpHtmlErrorFormat(): void {
		$body = '<br />'."\n".'<b>Deprecated</b>:  Function utf8_decode() is deprecated since 8.2'
			.' in <b>/var/www/html/include/functions.php</b> on line <b>935</b><br />';

		$this->assertSame(
			array('Deprecated: Function utf8_decode() is deprecated since 8.2 in /var/www/html/include/functions.php on line 935'),
			smoke_diagnostics($body)
		);
	}

	public function testItReadsThePlainTextErrorFormat(): void {
		$body = "Warning: session_start(): Session cannot be started in /var/www/html/include/functions.php on line 65\n";

		$this->assertSame(
			array('Warning: session_start(): Session cannot be started in /var/www/html/include/functions.php on line 65'),
			smoke_diagnostics($body)
		);
	}

	public function testRepeatedDiagnosticsAreReportedOnce(): void {
		$line = "Notice: Undefined thing in /var/www/html/index.php on line 3\n";

		$this->assertCount(1, smoke_diagnostics($line.$line.$line));
	}

	public function testEverySeverityIsRecognised(): void {
		$body = "Fatal error: boom in /a.php on line 1\n"
			."Parse error: syntax error in /b.php on line 2\n"
			."Warning: careful in /c.php on line 3\n"
			."Deprecated: old in /d.php on line 4\n"
			."Notice: fyi in /e.php on line 5\n";

		$this->assertCount(5, smoke_diagnostics($body));
	}

	public function testOnlyFatalsAndParseErrorsCountAsFatal(): void {
		$this->assertTrue(smoke_is_fatal('Fatal error: Uncaught Error: Undefined constant "X"'));
		$this->assertTrue(smoke_is_fatal('Parse error: syntax error, unexpected token'));
		$this->assertFalse(smoke_is_fatal('Deprecated: Function utf8_decode() is deprecated since 8.2'));
		$this->assertFalse(smoke_is_fatal('Warning: Cannot modify header information'));
		$this->assertFalse(smoke_is_fatal('Notice: something'));
	}

	public function testTheWordFatalInPageContentIsNotADiagnostic(): void {
		$this->assertSame(array(), smoke_diagnostics('<p>A fatal error in the plot ruined the novel.</p>'));
	}

	public function testEveryEntryPointNamedByThePlanIsSwept(): void {
		$swept = array_map(static fn(string $target): string => strtok($target, '?'), smoke_targets());

		foreach (array(
			'index.php', 'viewforum.php', 'viewtopic.php', 'post.php', 'login.php', 'register.php',
			'profile.php', 'search.php', 'userlist.php', 'misc.php', 'moderate.php', 'extern.php',
			'help.php', 'admin/index.php',
		) as $entry_point)
			$this->assertContains($entry_point, $swept);
	}

	public function testRequiringTheScriptDoesNotRunTheSweep(): void {
		// The sweep is guarded so the file can be required by this test; if the guard
		// broke, setUpBeforeClass() would have hung on HTTP requests or exited.
		$this->assertTrue(function_exists('smoke_main'));
	}
}
