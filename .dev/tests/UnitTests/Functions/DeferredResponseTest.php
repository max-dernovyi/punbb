<?php
/**
 * forum_end_page(): the whole page goes out before the deferred work runs, and
 * one failing job does not skip the next.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class DeferredResponseTest extends TestCase {
	private static function end(string $mode): string {
		return (string) shell_exec(escapeshellarg(PHP_BINARY).' -d display_errors=1 -d error_reporting=-1 '.escapeshellarg(__DIR__.'/deferred_response_harness.php').' '.escapeshellarg($mode).' 2>&1');
	}

	public function testThePageIsSentWholeBeforeTheQueueRunsAndAFailedJobSkipsNothing(): void {
		$output = self::end('deferred');

		$this->assertStringStartsWith('[printed early][body][job one, seeing the response sent]', $output, $output);
		$this->assertMatchesRegularExpression('#\] PunBB deferred work failed: probe failure\s*\[job two\]\[end transaction\]\[close\]\z#', $output, $output);
	}

	public function testAPageThatDefersNothingEndsWithItsBodyAfterTheDatabase(): void {
		$this->assertSame('[printed early][end transaction][close][body]', self::end('plain'));
	}
}
