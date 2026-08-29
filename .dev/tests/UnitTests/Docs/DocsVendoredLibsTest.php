<?php
/**
 * Keeps the documentation in sync with the two library replacements: phputf8
 * -> include/utf8.php (mbstring) and idna_convert -> ext-intl (UTS-46).
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocsVendoredLibsTest extends TestCase
{
	private function doc(string $name): string
	{
		$path = FORUM_ROOT.$name;
		$this->assertFileExists($path);

		return file_get_contents($path);
	}

	public function testReadmeListsEveryRequiredExtension(): void
	{
		$readme = $this->doc('README.md');

		foreach (forum_required_extensions() as $extension)
			$this->assertStringContainsString($extension, $readme, $extension.' is not listed in README.md');
	}

	public function testChangeLogRecordsBothReplacements(): void
	{
		$changelog = $this->doc('ChangeLog');

		$this->assertStringContainsString('phputf8', $changelog);
		$this->assertStringContainsString('include/utf8.php', $changelog);
		$this->assertStringContainsString('idna_convert', $changelog);
		$this->assertStringContainsString('UTS-46', $changelog);
		$this->assertStringContainsString('IDNA2003', $changelog);
	}

	/** @return array<string, array{string}> */
	public static function creditFiles(): array
	{
		return array('humans.txt' => array('humans.txt'), 'COPYING' => array('COPYING'));
	}

	/**
	 * Neither file ever credited the bundled libraries - their notices lived in
	 * the removed files themselves - so both must stay free of them.
	 */
	#[DataProvider('creditFiles')]
	public function testNoCreditsRemainForTheRemovedLibraries(string $name): void
	{
		$this->assertDoesNotMatchRegularExpression(
			'#phputf8|idna_convert|include/(utf8|idna)/#i',
			$this->doc($name),
			$name.' still credits a removed bundled library'
		);
	}
}
