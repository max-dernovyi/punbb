<?php
/**
 * The utf8_encode() replacement in admin/db_update.php.
 *
 * utf8_encode() mapped every ISO-8859-1 byte to the codepoint of the same
 * value; mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1') does exactly that
 * and is not deprecated. This pins the equivalence over the whole byte range
 * and guards the two deprecated functions out of the served source.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class Latin1ConversionTest extends TestCase {
	private function convert(string $str): string {
		return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
	}

	public function testEveryLatin1ByteBecomesTheCodepointOfTheSameValue(): void {
		for ($byte = 0; $byte < 256; $byte++)
		{
			$expected = mb_chr($byte, 'UTF-8');

			$this->assertSame($expected, $this->convert(chr($byte)), 'byte 0x'.dechex($byte));
		}
	}

	public function testAsciiSurvivesUnchanged(): void {
		$this->assertSame('PunBB 1.4.4', $this->convert('PunBB 1.4.4'));
		$this->assertSame('', $this->convert(''));
	}

	public function testTheResultIsValidUtf8(): void {
		$latin1 = implode('', array_map('chr', range(128, 255)));

		$this->assertTrue(mb_check_encoding($this->convert($latin1), 'UTF-8'));
	}

	public function testDbUpdateConvertsThroughMbstring(): void {
		$source = (string)file_get_contents(FORUM_ROOT.'admin/db_update.php');

		$this->assertStringContainsString("mb_convert_encoding(\$str, 'UTF-8', \$old_charset)", $source);
	}

	/** @return list<string> every PHP file the forum serves, minus the vendored libraries */
	public static function sources(): array {
		$files = array_merge(
			(array)glob(FORUM_ROOT.'*.php'),
			(array)glob(FORUM_ROOT.'admin/*.php'),
			(array)glob(FORUM_ROOT.'include/*.php')
		);

		return array_map(
			static fn (string $file): array => array(substr($file, strlen(FORUM_ROOT))),
			array_values(array_filter($files, 'is_string'))
		);
	}

	#[DataProvider('sources')]
	public function testNoApplicationFileCallsTheDeprecatedUtf8Functions(string $file): void {
		$source = (string)file_get_contents(FORUM_ROOT.$file);

		$this->assertDoesNotMatchRegularExpression('/(?<![\w\'"])utf8_(?:en|de)code\s*\(/', $source, $file);
	}
}
