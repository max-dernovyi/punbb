<?php
/**
 * The legacy chrome's "database is newer" alert: a board a later release
 * updated records a release above FORUM_VERSION, while FORUM_DB_REVISION no
 * longer moves between releases.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\LegacyBridge\Hook\HookMap;
use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyTemplate;

class LegacyChromeSourceTest extends TestCase {
	private mixed $config = null;

	protected function setUp(): void {
		$this->config = $GLOBALS['forum_config'] ?? null;
	}

	protected function tearDown(): void {
		if ($this->config === null)
			unset($GLOBALS['forum_config']);
		else
			$GLOBALS['forum_config'] = $this->config;
	}

	/** @return array<string, array{array<string, string>, bool}> */
	public static function recordedProvider(): array {
		return array(
			'a later release'				=> array(array('o_cur_version' => '2.0.1', 'o_database_revision' => '7'), true),
			'this release'					=> array(array('o_cur_version' => FORUM_VERSION, 'o_database_revision' => '7'), false),
			'an earlier release'			=> array(array('o_cur_version' => '1.5.1', 'o_database_revision' => '6'), false),
			'no release recorded'			=> array(array(), false),
			'a revision above this one'		=> array(array('o_cur_version' => FORUM_VERSION, 'o_database_revision' => '8'), false),
		);
	}

	/** @param array<string, string> $config */
	#[DataProvider('recordedProvider')]
	public function testTheDatabaseIsNewerWhenALaterReleaseUpdatedIt(array $config, bool $newer): void {
		$GLOBALS['forum_config'] = $config;

		$source = new LegacyChromeSource(new PointEvaluator(new HookMap(), static fn (): string => ''), new LegacyTemplate());

		$this->assertSame($newer, $source->board()->databaseIsNewer);
	}
}
