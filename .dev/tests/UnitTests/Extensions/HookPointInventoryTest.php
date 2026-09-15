<?php
/**
 * The hook points the tree offers do not quietly disappear.
 *
 * An extension attaches to a point by name, and attaching to a point nothing
 * offers is accepted at install and then never fires. So a point removed from
 * the code breaks every extension on it without an error anywhere. The
 * inventory at .dev/tests/fixtures/hook_points.txt lists every point; a removal
 * keeps its line with a note, and the listed count never falls.
 *
 * A point is offered by a get_hook('<id>') site, by the bridge running it by
 * name, or by the event the bridge's mapping table says covers it.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\LegacyBridge\Hook\HookMap;

class HookPointInventoryTest extends TestCase {
	private const INVENTORY = FORUM_ROOT.'.dev/tests/fixtures/hook_points.txt';

	/** Distinct points in the tree when the inventory was taken. */
	private const FLOOR = 1709;

	/** Top-level directories that are not the forum's own code. */
	private const NOT_SCANNED = array('vendor', 'cache', 'extensions', 'docs', 'img');

	private const SITE_PATTERN = '/get_hook\(\'([A-Za-z0-9_]+)\'\)/';

	/** Where the bridge runs a point by name, through a runner. */
	private const BRIDGE = 'include/PunBB/Module/LegacyBridge/';

	private const BRIDGE_SITE_PATTERN = '/->(?:run|render)\(\'([A-Za-z0-9_]+)\'/';

	/** @var array<string, true>|null */
	private static ?array $tree = null;

	/** @return array<string, true> every point a get_hook('<id>') site offers */
	private static function tree(): array {
		if (self::$tree !== null)
			return self::$tree;

		$files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator(FORUM_ROOT, FilesystemIterator::SKIP_DOTS),
			function (SplFileInfo $file, string $path, RecursiveDirectoryIterator $iterator): bool {
				$name = $file->getFilename();

				if ($iterator->getSubPath() === '' && ($name[0] === '.' || $name === 'config.php' || in_array($name, self::NOT_SCANNED, true)))
					return false;

				return $file->isDir() || $file->getExtension() === 'php';
			}
		));

		self::$tree = array_fill_keys(array_keys(HookMap::COVERED), true);
		foreach ($files as $file)
		{
			$source = (string) file_get_contents($file->getPathname());

			preg_match_all(self::SITE_PATTERN, $source, $matches);
			self::$tree += array_fill_keys($matches[1], true);

			if (str_starts_with(substr($file->getPathname(), strlen(FORUM_ROOT)), self::BRIDGE))
			{
				preg_match_all(self::BRIDGE_SITE_PATTERN, $source, $matches);
				self::$tree += array_fill_keys($matches[1], true);
			}
		}

		return self::$tree;
	}

	/** @return array<string, string> point => removal note, '' for a live point */
	private static function inventory(): array {
		$entries = array();

		foreach (file(self::INVENTORY, FILE_IGNORE_NEW_LINES) as $line)
		{
			if ($line === '' || $line[0] === '#')
				continue;

			$parts = explode(' -- ', $line, 2);
			$entries[$parts[0]] = trim($parts[1] ?? '');
		}

		return $entries;
	}

	public function testTheInventoryHasNoDuplicateLines(): void {
		$lines = preg_grep('/^[^#]/', file(self::INVENTORY, FILE_IGNORE_NEW_LINES));

		$this->assertSame(count($lines), count(self::inventory()));
	}

	public function testTheListedCountDoesNotFall(): void {
		$this->assertGreaterThanOrEqual(self::FLOOR, count(self::inventory()),
			'a line left the inventory: a removed point keeps its line as "<id> -- <why>"');
	}

	public function testEveryListedPointIsStillOffered(): void {
		$tree = self::tree();

		foreach (self::inventory() as $point => $note)
			if ($note === '')
				$this->assertArrayHasKey($point, $tree,
					$point.' is no longer offered: every extension attached to it stops firing. If that is deliberate, mark its line "'.$point.' -- <why>"');
	}

	public function testEveryPointInTheTreeIsListed(): void {
		$missing = array_diff_key(self::tree(), self::inventory());

		$this->assertSame(array(), array_keys($missing), 'new hook points: add them to '.self::INVENTORY);
	}

	public function testARemovedPointIsNotOfferedAgain(): void {
		$removed = array_filter(self::inventory(), fn (string $note): bool => $note !== '');

		$this->assertSame(array(), array_keys(array_intersect_key($removed, self::tree())), 'offered again: drop the removal note');
	}

	/** The one point built at runtime, which the literal scan cannot see. */
	public function testTheDynamicRepositoryPointIsStillOffered(): void {
		$source = (string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/LegacyBridge/Page/Extensions/LegacyUpdates.php');

		$this->assertStringContainsString("\$point = 'aex_add_repository_for_'.\$id;\n\t\t\t\$this->scope->run(\$point,", $source);
	}

	/** The scan is worth nothing if it misses a site shape the tree uses. */
	public function testTheScanSeesStatementAndInlineSites(): void {
		$tree = self::tree();

		$this->assertArrayHasKey('es_essentials', $tree);
		$this->assertArrayHasKey('fn_get_remote_address_start', $tree);
		$this->assertArrayHasKey('vt_row_new_post_entry_data', $tree);
		$this->assertArrayHasKey('hd_template_loaded', $tree, 'a point the bridge runs by name');
		$this->assertArrayHasKey('ft_about_end', $tree, 'a point an event covers');
		$this->assertArrayNotHasKey('punbb_fixture_banner_pre_output', $tree, 'the scan must not reach the test fixtures');
	}
}
