<?php
/**
 * message() is called by extension code, which may pass null or a non-string.
 * Html takes a string only, so every value is normalised before it is wrapped.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class MessageArgumentsTest extends TestCase
{
	private function messageBody(): string
	{
		$source = file_get_contents(FORUM_ROOT.'include/functions.php');
		$this->assertMatchesRegularExpression('/^function message\(/m', $source);

		$start = strpos($source, "\nfunction message(");
		$end = strpos($source, "\nfunction ", $start + 1);

		return substr($source, $start, $end - $start);
	}

	public function testEveryArgumentIsNormalisedBeforeItIsWrapped(): void
	{
		$body = $this->messageBody();
		$wrapped = strpos($body, 'new PunBB\\Module\\Layout\\View\\Html(');

		foreach (array('message', 'link', 'heading') as $name)
		{
			$normalised = strpos($body, '$'.$name.' = PunBB\\Module\\LegacyBridge\\Layout\\Markers::markup($'.$name.');');
			$this->assertNotFalse($normalised, '$'.$name.' reaches Html unnormalised');
			$this->assertLessThan($wrapped, $normalised);
		}

		$this->assertStringContainsString('Html(PunBB\\Module\\LegacyBridge\\Layout\\Markers::markup($option))', $body);
	}

	public function testMarkupTurnsWhatHtmlRefusesIntoAString(): void
	{
		$markup = PunBB\Module\LegacyBridge\Layout\Markers::markup(...);

		$this->assertSame('', $markup(null));
		$this->assertSame('', $markup(array('x')));
		$this->assertSame('5', $markup(5));
		$this->assertSame('<b>x</b>', $markup('<b>x</b>'));
	}
}
