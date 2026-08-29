<?php
/**
 * Regression guard for nullable columns reaching the parser during an upgrade.
 *
 * `posts.message` and `users.signature` are declared `allow_null`, so the rows
 * db_update.php preparses can hand `preparse_bbcode()` a null — deprecated in
 * PHP 8.1 and only reachable on the 1.2/1.3 conversion path, which no request
 * sweep visits. The reads are normalised where they happen, per the "fix nulls
 * at the source" rule; this pins both the read and the parser's contract.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class NullableColumnGuardTest extends TestCase
{
	/** Every `preparse_bbcode($cur_item[...]` read, with whatever follows the key. */
	private const READ_PATTERN = '/preparse_bbcode\s*\(\s*(\$cur_item\[[^\]]+\])\s*(\?\?)?/';

	private function updateScript(): string
	{
		return (string) file_get_contents(FORUM_ROOT.'admin/db_update.php');
	}

	public function testUpgradeReadsOfNullableColumnsCarryADefault(): void
	{
		preg_match_all(self::READ_PATTERN, $this->updateScript(), $matches, PREG_SET_ORDER);

		$this->assertNotEmpty($matches, 'db_update.php no longer preparses rows: retarget this guard');

		foreach ($matches as $match)
			$this->assertSame('??', $match[2] ?? '',
				$match[1].' is a nullable column handed to preparse_bbcode() without a default');
	}

	//
	// The scan is worth nothing if its pattern cannot see an offender, so run
	// it against one.
	//
	public function testTheGuardSeesAnUnguardedRead(): void
	{
		$sample = '$x = preparse_bbcode($cur_item[\'signature\'], $errors, true);';

		$this->assertSame(1, preg_match(self::READ_PATTERN, $sample, $match));
		$this->assertArrayNotHasKey(2, $match, 'an unguarded read must not report a default');
	}

	//
	// The other half of the contract: the value the fix substitutes has to
	// survive the parser unchanged, so an empty signature stays empty.
	//
	public function testEmptyStringPreparsesToEmptyString(): void
	{
		$errors = array();

		$this->assertSame('', preparse_bbcode('', $errors, true));
		$this->assertSame(array(), $errors);

		$errors = array();

		$this->assertSame('', preparse_bbcode('', $errors));
		$this->assertSame(array(), $errors);
	}
}
