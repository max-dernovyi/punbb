<?php
/**
 * validate_manifest() as admin/extensions.php calls it: the manifest text goes
 * through xml_to_array() and the folder name is the id the install asked for.
 * Any error it returns stops the install with "Bad request".
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExtensionManifestTest extends TestCase {
	private const FIXTURES = FORUM_ROOT.'.dev/tests/fixtures/extensions/';

	/** @var array<string, mixed> */
	private array $savedConfig;

	protected function setUp(): void {
		global $forum_config, $lang_admin_ext;

		require_once FORUM_ROOT.'include/xml.php';
		require FORUM_ROOT.'lang/English/admin_ext.php';

		$this->savedConfig = $forum_config;
		$forum_config['o_cur_version'] = FORUM_VERSION;
	}

	protected function tearDown(): void {
		global $forum_config;

		$forum_config = $this->savedConfig;
		unset($GLOBALS['lang_admin_ext']);
	}

	public static function fixtures(): array {
		return array(
			'punbb_fixture'		=> array('punbb_fixture'),
			'punbb_fixture_dep'	=> array('punbb_fixture_dep')
		);
	}

	/** @return array<string, array{string, string}> element dropped, error it must raise */
	public static function requiredElements(): array {
		return array(
			'id'			=> array('id', 'extension/id error'),
			'version'		=> array('version', 'extension/version error'),
			'minversion'	=> array('minversion', 'extension/minversion error')
		);
	}

	private static function manifest(string $id): string {
		return (string) file_get_contents(self::FIXTURES.$id.'/manifest.xml');
	}

	/** @return list<string> */
	private static function errors(string $manifest, string $folder): array {
		return validate_manifest(xml_to_array($manifest), $folder);
	}

	#[DataProvider('fixtures')]
	public function testTheFixtureManifestIsValid(string $id): void {
		$this->assertSame(array(), self::errors(self::manifest($id), $id));
	}

	#[DataProvider('requiredElements')]
	public function testAManifestMissingARequiredElementIsRejected(string $element, string $error): void {
		global $lang_admin_ext;

		$manifest = preg_replace('#\s*<'.$element.'>[^<]*</'.$element.'>#', '', self::manifest('punbb_fixture'), -1, $count);
		$this->assertSame(1, $count, 'the fixture manifest has no <'.$element.'> to drop');

		$this->assertContains($lang_admin_ext[$error], self::errors($manifest, 'punbb_fixture'));
	}
}
