<?php

use PHPUnit\Framework\TestCase;

class OrdTest extends TestCase {
	public function testOrdAscii(): void {
		$this->assertSame(0, utf8_ord("\x00"));
		$this->assertSame(65, utf8_ord('A'));
		$this->assertSame(127, utf8_ord("\x7F"));
	}

	public function testOrdMultibyte(): void {
		$this->assertSame(0x424, utf8_ord('Ф'));
		$this->assertSame(0x5C01, utf8_ord('封'));
		$this->assertSame(0x1F600, utf8_ord('😀'));
	}

	// 5- and 6-byte forms are no longer valid UTF-8, but the arithmetic must
	// still be correct: the 6-byte branch read an undefined variable.
	public function testOrdFiveAndSixByteSequences(): void {
		$this->assertSame(1048576, utf8_ord("\xF8\x84\x80\x80\x80"));
		$this->assertSame(67108864, utf8_ord("\xFC\x84\x80\x80\x80\x80"));
		$this->assertSame(67108865, utf8_ord("\xFC\x84\x80\x80\x80\x81"));
	}

	public function testOrdShortSequenceTriggersError(): void {
		$notices = array();
		set_error_handler(function ($no, $str) use (&$notices) {
			$notices[] = $str;
			return TRUE;
		}, E_USER_NOTICE);

		try {
			$this->assertFalse(utf8_ord("\xC3"));
			$this->assertFalse(utf8_ord("\xE3\x81"));
		} finally {
			restore_error_handler();
		}

		$this->assertSame(array(
			'Short sequence - at least 2 bytes expected, only 1 seen',
			'Short sequence - at least 3 bytes expected, only 2 seen',
		), $notices);
	}
}
