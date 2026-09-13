<?php
/**
 * The maxtestedon gate in admin/extensions.php.
 *
 * 1.5 keeps the 1.4 extension contract, so an extension tested up to any 1.4.x
 * installs; one tested only on an older line is refused. Driven through the
 * real page on a scratch SQLite3 forum.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/ScratchForum.php';

class ExtensionVersionGateTest extends TestCase {
	/** @return array<string, array{string, string, bool}> forum version, maxtestedon, accepted */
	public static function versions(): array {
		return array(
			'1.4 on 1.5'			=> array('1.5.1', '1.4', true),
			'1.4.2 on 1.5'			=> array('1.5.1', '1.4.2', true),
			'1.4.4 on 1.5'			=> array('1.5.1', '1.4.4', true),
			'1.5 on 1.5'			=> array('1.5.1', '1.5', true),
			'1.5.0 on 1.5'			=> array('1.5.1', '1.5.0', true),
			'1.6 on 1.5'			=> array('1.5.1', '1.6', true),
			'1.3.6 on 1.5'			=> array('1.5.1', '1.3.6', false),
			'1.2 on 1.5'			=> array('1.5.1', '1.2', false),
			'a bare core on 1.5'	=> array('1.5.1', '1', false),
			'2 on 1.5'				=> array('1.5.1', '2', true),
			'1.3 on 1.3'			=> array('1.3.6', '1.3', true),
		);
	}

	#[DataProvider('versions')]
	public function testTheGateComparesReleaseLines(string $forum, string $maxtestedon, bool $accepted): void {
		$this->assertSame($accepted, forum_extension_version_supported($forum, $maxtestedon));
	}

	/** @return array<string, array{string, bool}> maxtestedon, installs */
	public static function installs(): array {
		return array(
			'1.4.4'	=> array('1.4.4', true),
			'1.4'	=> array('1.4', true),
			'1.5'	=> array('1.5', true),
			'1.3.6'	=> array('1.3.6', false),
		);
	}

	#[DataProvider('installs')]
	public function testInstallHonoursTheGate(string $maxtestedon, bool $installs): void {
		if (!class_exists('SQLite3'))
			$this->markTestSkipped('the scratch forum needs ext-sqlite3');

		require FORUM_ROOT.'lang/English/admin_ext.php';

		$forum = new ScratchForum();

		try
		{
			$forum->writeExtension('gate_probe', '<?xml version="1.0" encoding="utf-8"?>'."\n".
				'<extension engine="1.0"><id>gate_probe</id><title>Gate probe</title><version>0.1</version>'.
				'<description>Probe</description><author>Test</author><minversion>1.4</minversion>'.
				'<maxtestedon>'.$maxtestedon.'</maxtestedon></extension>');

			$page = $forum->submit('admin/extensions.php', array('install' => 'gate_probe'), array('install_comply' => '1'));

			foreach (array('Fatal error', 'Warning:', 'Deprecated:', 'Notice:') as $marker)
				$this->assertStringNotContainsString($marker, $page, $page);

			$this->assertSame($installs, str_contains($page, $lang_admin_ext['Extension installed']), $page);
			$this->assertSame(!$installs, str_contains($page, $lang_admin_ext['Maxtestedon error']), $page);
			$this->assertCount($installs ? 1 : 0, $forum->rows('SELECT id FROM extensions WHERE id=\'gate_probe\''));
		}
		finally
		{
			$forum->remove();
		}
	}
}
