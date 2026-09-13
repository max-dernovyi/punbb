<?php
/**
 * admin/extensions.php keeps an extension after what it depends on.
 *
 * Install refuses while a <dependency> is not installed and enabled; uninstall
 * refuses while any extension, enabled or disabled, depends on the one going.
 * Driven through the real page on a scratch SQLite3 forum, with punbb_fixture_dep
 * depending on punbb_fixture.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/ScratchForum.php';

class ExtensionDependencyOrderTest extends TestCase {
	private ScratchForum $forum;

	/** @var array<string, string> */
	private array $lang;

	protected function setUp(): void {
		if (!class_exists('SQLite3'))
			$this->markTestSkipped('the scratch forum needs ext-sqlite3');

		require FORUM_ROOT.'lang/English/admin_ext.php';
		$this->lang = $lang_admin_ext;

		$this->forum = new ScratchForum();
		$this->forum->addExtension('punbb_fixture');
		$this->forum->addExtension('punbb_fixture_dep');
	}

	protected function tearDown(): void {
		if (isset($this->forum))
			$this->forum->remove();
	}

	private function install(string $id): string {
		return $this->page($this->forum->submit('admin/extensions.php', array('install' => $id), array('install_comply' => '1')));
	}

	private function uninstall(string $id): string {
		return $this->page($this->forum->submit('admin/extensions.php', array('section' => 'manage', 'uninstall' => $id), array('uninstall_comply' => '1')));
	}

	private function flip(string $id): string {
		return $this->page($this->forum->flip($id));
	}

	private function page(string $output): string {
		foreach (array('Fatal error', 'Uncaught', 'Warning:', 'Deprecated:', 'Notice:', 'Sorry! The page could not be loaded.') as $marker)
			$this->assertStringNotContainsString($marker, $output, $output);

		return $output;
	}

	/** @return array<string, array{disabled: int, dependencies: string}> */
	private function installed(): array {
		$installed = array();
		foreach ($this->forum->rows('SELECT id, disabled, dependencies FROM extensions') as $row)
			$installed[$row['id']] = array('disabled' => (int) $row['disabled'], 'dependencies' => $row['dependencies']);

		return $installed;
	}

	private function hookRows(string $id): int {
		return count($this->forum->rows('SELECT id FROM extension_hooks WHERE extension_id=\''.$id.'\''));
	}

	public function testADependentIsRefusedBeforeItsDependency(): void {
		$page = $this->install('punbb_fixture_dep');

		$this->assertStringContainsString(sprintf($this->lang['Missing dependency'], 'punbb_fixture'), $page);
		$this->assertSame(array(), $this->installed());
		$this->assertSame(0, $this->hookRows('punbb_fixture_dep'));
	}

	public function testADisabledDependencyDoesNotSatisfyTheInstall(): void {
		$this->install('punbb_fixture');
		$this->flip('punbb_fixture');
		$this->assertSame(1, $this->installed()['punbb_fixture']['disabled']);

		$page = $this->install('punbb_fixture_dep');

		$this->assertStringContainsString(sprintf($this->lang['Missing dependency'], 'punbb_fixture'), $page);
		$this->assertArrayNotHasKey('punbb_fixture_dep', $this->installed());
	}

	public function testTheDependentInstallsAfterItsDependency(): void {
		$this->assertStringContainsString($this->lang['Extension installed'], $this->install('punbb_fixture'));
		$this->assertStringContainsString($this->lang['Extension installed'], $this->install('punbb_fixture_dep'));

		$this->assertSame(array(
			'punbb_fixture'		=> array('disabled' => 0, 'dependencies' => '||'),
			'punbb_fixture_dep'	=> array('disabled' => 0, 'dependencies' => '|punbb_fixture|')
		), $this->installed());
		$this->assertSame(2, $this->hookRows('punbb_fixture_dep'));
	}

	public function testUninstallIsRefusedWhileADependentIsInstalled(): void {
		$this->install('punbb_fixture');
		$this->install('punbb_fixture_dep');

		$page = $this->uninstall('punbb_fixture');

		$this->assertStringContainsString(sprintf($this->lang['Uninstall dependency'], 'punbb_fixture_dep'), $page);
		$this->assertArrayHasKey('punbb_fixture', $this->installed());
		$this->assertCount(1, $this->forum->rows('SELECT name FROM sqlite_master WHERE type=\'table\' AND name=\'punbb_fixture_markers\''), '<uninstall> ran for a refused uninstall');
	}

	public function testUninstallIsRefusedWhileTheDependentIsDisabled(): void {
		$this->install('punbb_fixture');
		$this->install('punbb_fixture_dep');
		$this->flip('punbb_fixture_dep');
		$this->assertSame(1, $this->installed()['punbb_fixture_dep']['disabled']);

		$page = $this->uninstall('punbb_fixture');

		$this->assertStringContainsString(sprintf($this->lang['Uninstall dependency'], 'punbb_fixture_dep'), $page);
		$this->assertArrayHasKey('punbb_fixture', $this->installed());
	}

	public function testTheDependentUninstallsFirstAndThenItsDependency(): void {
		$this->install('punbb_fixture');
		$this->install('punbb_fixture_dep');

		$this->assertStringContainsString($this->lang['Extension uninstalled'], $this->uninstall('punbb_fixture_dep'));
		$this->assertStringContainsString($this->lang['Extension uninstalled'], $this->uninstall('punbb_fixture'));

		$this->assertSame(array(), $this->installed());
		$this->assertSame(0, $this->hookRows('punbb_fixture') + $this->hookRows('punbb_fixture_dep'));
	}
}
