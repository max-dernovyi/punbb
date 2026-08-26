<?php
/**
 * Extensions hooking ps_do_bbcode_replace push replacement *code strings*
 * into $replace / $replace_callback. Core now pushes closures, but the string
 * form has to keep working — forum_bbcode_replace_callable() compiles it.
 *
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class LegacyReplaceContractTest extends TestCase
{
	public function testClosureIsPassedThrough(): void
	{
		$closure = function ($matches) { return 'x'; };

		$this->assertSame($closure, forum_bbcode_replace_callable($closure));
	}

	public function testLegacyStringTemplateIsCompiled(): void
	{
		$callable = forum_bbcode_replace_callable('<span class=\"legacy\">$matches[1]</span>');

		$this->assertSame(
			'<span class="legacy">hi</span>',
			preg_replace_callback('#\[legacy\](.*?)\[/legacy\]#', $callable, '[legacy]hi[/legacy]')
		);
	}

	// create_function() compiled every string, so a replacement that happens to
	// name a function is still a literal, never a call.
	public function testFunctionNameStringIsTreatedAsLiteral(): void
	{
		$callable = forum_bbcode_replace_callable('strtoupper');

		$this->assertSame(
			'strtoupper',
			preg_replace_callback('#\[legacy\](.*?)\[/legacy\]#', $callable, '[legacy]hi[/legacy]')
		);
	}

	public function testLegacyExpressionIsCompiled(): void
	{
		$callable = forum_bbcode_replace_callable('strtoupper($matches[1])', true);

		$this->assertSame(
			'HI',
			preg_replace_callback('#\[legacy\](.*?)\[/legacy\]#', $callable, '[legacy]hi[/legacy]')
		);
	}
}
