<?php
/**
 * forum_microtime() after the PHP 4 string-microtime fallback was removed.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class ForumMicrotimeTest extends TestCase {
	public function testReturnsAFloatTimestamp(): void {
		$mt = forum_microtime();

		$this->assertIsFloat($mt);
		$this->assertEqualsWithDelta(time(), $mt, 5.0);
	}

	public function testKeepsSubSecondResolution(): void {
		$before = forum_microtime();
		usleep(2000);
		$after = forum_microtime();

		$this->assertGreaterThan($before, $after);
		$this->assertLessThan(1.0, $after - $before);
	}

	public function testDoesNotFallBackToTheStringForm(): void {
		$source = file_get_contents(FORUM_ROOT.'include/functions.php');
		$body = substr($source, strpos($source, 'function forum_microtime'));
		$body = substr($body, 0, strpos($body, "\n}"));

		$this->assertStringNotContainsString('explode', $body, 'the PHP 4 microtime() string parsing must be gone');
		$this->assertStringNotContainsString('version_compare', $body);
	}
}
