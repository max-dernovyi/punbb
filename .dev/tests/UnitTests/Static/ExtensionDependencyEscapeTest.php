<?php
/**
 * Regression guard for the dependency list an extension manifest supplies.
 *
 * `extensions.dependencies` holds the manifest's `<dependency>` ids as one
 * pipe-delimited string, so the value is assembled in admin/extensions.php
 * rather than handed to the query builder as a column of its own — and it was
 * interpolated into the INSERT and the UPDATE without going through escape().
 * Nothing reaches it from a request (the manifest is admin-installed, and the
 * install refuses a dependency that is not already an installed extension id),
 * but it is the one string the two statements build by hand.
 *
 * The fix escapes each id where the list is built; this pins that both writers
 * use the escaped list, because the raw one is still in scope beside it.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class ExtensionDependencyEscapeTest extends TestCase
{
	/** Every `'|'.implode('|', ...).'|'` list the two writers build, with whatever it imploded. */
	private const WRITE_PATTERN = '/\\\\\'\|\'\.implode\(\'\|\', ([^)]+)\)\.\'\|/';

	private function extensionsScript(): string
	{
		return (string) file_get_contents(FORUM_ROOT.'admin/extensions.php');
	}

	public function testBothWritersUseTheEscapedDependencyList(): void
	{
		preg_match_all(self::WRITE_PATTERN, $this->extensionsScript(), $matches, PREG_SET_ORDER);

		$this->assertCount(2, $matches, 'extensions.php no longer writes the dependency list twice: retarget this guard');

		foreach ($matches as $match)
			$this->assertSame('$dependencies', $match[1],
				$match[1].' reaches the dependencies column without being escaped');
	}

	public function testTheEscapedListIsBuiltThroughEscape(): void
	{
		$this->assertMatchesRegularExpression(
			'/\$dependencies\[\] = \$forum_db->escape\(/',
			$this->extensionsScript(),
			'$dependencies is no longer built through escape()'
		);
	}

	//
	// The scan is worth nothing if its pattern cannot see an offender, so run
	// it against the shape the code had before the fix.
	//
	public function testTheGuardSeesAnUnescapedWrite(): void
	{
		$sample = '\'SET\' => \'dependencies=\\\'|\'.implode(\'|\', $ext_data[\'extension\'][\'dependencies\']).\'|\\\'\',';

		$this->assertSame(1, preg_match(self::WRITE_PATTERN, $sample, $match));
		$this->assertNotSame('$dependencies', $match[1]);
	}
}
