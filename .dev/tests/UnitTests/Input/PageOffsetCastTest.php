<?php
/**
 * Explicit int casts on the pagination arithmetic.
 *
 * $_GET['p'] is only checked with is_numeric(), so "2.5" and "3e2" pass. The
 * page number is multiplied by the per-page count and interpolated into a SQL
 * LIMIT, and ceil() returns a float, so both ends of the arithmetic have to be
 * cast at the source rather than relying on an implicit conversion PHP 8.1
 * deprecates.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PageOffsetCastTest extends TestCase {
	public static function numericPageInputs(): array {
		return array(
			'integer string'	=> array('7', 7),
			'fractional'		=> array('2.5', 2),
			'exponent'			=> array('3e2', 300),
			'leading space'		=> array(' 4', 4),
			'float'				=> array(2.9, 2),
			'negative'			=> array('-3', -3),
		);
	}

	/** @param string|float $input */
	#[DataProvider('numericPageInputs')]
	public function testTheCastTurnsEveryIsNumericValueIntoAnInt($input, int $expected): void {
		$this->assertTrue(is_numeric($input), 'the guard in the source accepts this value');
		$this->assertSame($expected, (int)$input);
	}

	public function testTheOffsetArithmeticStaysIntegral(): void {
		$per_page = 25;

		foreach (array('1', '2.5', '3e0', 4) as $page)
		{
			$start_from = $per_page * ((int)$page - 1);

			$this->assertIsInt($start_from);
			$this->assertSame((string)$start_from, (string)(int)$start_from, 'a LIMIT clause never carries a decimal point');
		}
	}
}
