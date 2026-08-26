<?php
/**
 * Unit tests for callback_autolink(), the helper the four do_clickable()
 * autolink callbacks share.
 *
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class CallbackAutolinkTest extends TestCase
{
	public function testSchemeSeparatorBuildsBbcodeLink(): void
	{
		$this->assertSame('[url]http://ya.ru/[/url]',
			callback_autolink(array('http://ya.ru/', '', '', '', '', 'http', 'ya.ru/'), '://'));
	}

	public function testHostSeparatorPrependsScheme(): void
	{
		$this->assertSame('[url=http://www.example.com]www.example.com[/url]',
			callback_autolink(array('www.example.com', '', '', '', '', 'www', 'example.com'), '.'));

		$this->assertSame('[url=ftp://ftp.example.com]ftp.example.com[/url]',
			callback_autolink(array('ftp.example.com', '', '', '', '', 'ftp', 'example.com'), '.'));
	}

	//
	// The pattern only ever fills the groups it actually matched, so the
	// helper has to tolerate a sparse $matches without emitting a warning.
	//
	public function testMissingTrailingGroupsArePadded(): void
	{
		$matches = array('http://ya.ru', '', '', '', '', 'http', 'ya.ru');

		$this->assertArrayNotHasKey(12, $matches);
		$this->assertSame('[url]http://ya.ru[/url]', callback_autolink($matches, '://'));
	}

	//
	// Groups 1-4 are the opening wrappers, 4 and 10-12 the closing ones; both
	// runs go through stripslashes() and are kept around the link.
	//
	public function testWrappingCharactersSurviveAroundTheLink(): void
	{
		$matches = array(0 => '["http://ya.ru"]', 1 => '', 2 => '[', 3 => '', 4 => '"',
			5 => 'http', 6 => 'ya.ru', 10 => '', 11 => ']', 12 => '');

		$this->assertSame('["[url]http://ya.ru[/url]"]', callback_autolink($matches, '://'));
	}

	public function testWrappingCharactersAreUnescaped(): void
	{
		$matches = array(0 => '', 1 => '', 2 => '', 3 => '', 4 => '\\"',
			5 => 'http', 6 => 'ya.ru', 10 => '', 11 => '', 12 => '');

		$this->assertSame('"[url]http://ya.ru[/url]"', callback_autolink($matches, '://'));
	}

	//
	// A match with an empty host still has to return a string rather than blow up.
	//
	public function testEmptyHostDoesNotFail(): void
	{
		$this->assertIsString(callback_autolink(array('http://', '', '', '', '', 'http', ''), '://'));
	}

	public function testUnicodeHostIsPunycodedInTheUrl(): void
	{
		$this->assertSame('[url=http://xn--d1acpjx3f.xn--p1ai]http://яндекс.рф[/url]',
			callback_autolink(array('', '', '', '', '', 'http', 'яндекс.рф'), '://'));
	}
}
