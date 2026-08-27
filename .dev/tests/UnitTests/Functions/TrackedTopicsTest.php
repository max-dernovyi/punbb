<?php
/**
 * get_tracked_topics() — the homebrew cookie deserializer.
 *
 * The `default:` arm of its switch used a bare `continue`, which PHP compiles
 * as `break`: an entry with an unknown prefix was not skipped, it fell through
 * and was filed under whatever $type the previous entry had set. These tests
 * pin the corrected behaviour.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class TrackedTopicsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['cookie_name'] = 'punbb_cookie';
		unset($_COOKIE['punbb_cookie_track']);
	}

	protected function tearDown(): void {
		unset($_COOKIE['punbb_cookie_track'], $GLOBALS['cookie_name']);
	}

	private function parse(string $cookie): array {
		$_COOKIE['punbb_cookie_track'] = $cookie;

		return get_tracked_topics();
	}

	public function testNoCookieYieldsEmptyLists(): void {
		$this->assertSame(array('topics' => array(), 'forums' => array()), get_tracked_topics());
	}

	public function testKnownPrefixesAreFiledUnderTheirOwnType(): void {
		$tracked = $this->parse('t1=1000;t2=1100;f3=1200;');

		$this->assertSame(array(1 => 1000, 2 => 1100), $tracked['topics']);
		$this->assertSame(array(3 => 1200), $tracked['forums']);
	}

	public function testAnUnknownPrefixIsSkippedInsteadOfInheritingTheLastType(): void {
		$tracked = $this->parse('t1=1000;x2=1100;');

		$this->assertSame(array(1 => 1000), $tracked['topics']);
		$this->assertSame(array(), $tracked['forums']);
	}

	public function testAnUnknownPrefixDoesNotDisturbLaterEntries(): void {
		$tracked = $this->parse('x9=900;f3=1200;t1=1000;');

		$this->assertSame(array(1 => 1000), $tracked['topics']);
		$this->assertSame(array(3 => 1200), $tracked['forums']);
	}

	public function testMalformedEntriesAreDropped(): void {
		// no '=' separator, no id, no timestamp, empty prefix, bare separator
		$tracked = $this->parse('t1;t=1000;t2=;f;;t4=1400;');

		$this->assertSame(array(4 => 1400), $tracked['topics']);
		$this->assertSame(array(), $tracked['forums']);
	}

	/** A cookie sent as name[key]=v arrives as an array, not a string. */
	public function testAnArrayCookieIsIgnored(): void {
		$_COOKIE['punbb_cookie_track'] = array('t1=1000;');

		$this->assertSame(array('topics' => array(), 'forums' => array()), get_tracked_topics());
	}

	public function testAnOversizedCookieIsIgnored(): void {
		$this->assertSame(
			array('topics' => array(), 'forums' => array()),
			$this->parse(str_repeat('t1=1000;', 700))
		);
	}
}
