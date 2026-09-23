<?php
/**
 * A module declares one version and a board records two for it, schema and
 * data, which move apart: a module never recorded is at zero on both, the
 * modules behind are listed by part, and a fresh install leaves none behind.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Version\InstalledVersion;
use PunBB\Module\Database\Version\InstalledVersionsInterface;
use PunBB\Module\Database\Version\ModuleVersions;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\ModuleTree;
use PunBB\Module\Framework\Modules\Wiring;

final class MemoryInstalledVersions implements InstalledVersionsInterface {
	/** @var array<string, InstalledVersion> */
	public array $versions = array();

	public function all(): array { return $this->versions; }

	public function recordSchema(string $module, string $version): void {
		$this->versions[$module] = new InstalledVersion($version, ($this->versions[$module] ?? new InstalledVersion())->data);
	}

	public function recordData(string $module, string $version): void {
		$this->versions[$module] = new InstalledVersion(($this->versions[$module] ?? new InstalledVersion())->schema, $version);
	}
}

class ModuleVersionsTest extends TestCase {
	private MemoryInstalledVersions $installed;

	protected function setUp(): void {
		$this->installed = new MemoryInstalledVersions();
	}

	private static function module(string $name, string $version): ModuleInterface {
		return new class($name, $version) implements ModuleInterface {
			public function __construct(private string $name, private string $version) {}

			public function name(): string { return $this->name; }

			public function dependencies(): array { return array(); }

			public function loadAfter(): array { return array(); }

			public function version(): string { return $this->version; }

			public function wire(Wiring $wiring): void {}
		};
	}

	private function versions(ModuleInterface ...$modules): ModuleVersions {
		return new ModuleVersions($this->installed, ...$modules);
	}

	public function testAModuleWithNoRecordedVersionReadsAsZero(): void {
		$versions = $this->versions(self::module('Polls', '1.0.0'));

		$this->assertEquals(new InstalledVersion('0', '0'), $versions->installed('Polls'));
		$this->assertSame(array(true, true), array($versions->schemaBehind('Polls'), $versions->dataBehind('Polls')));
		$this->assertSame(array('Polls'), $versions->behind());
	}

	public function testTheTwoVersionsMoveIndependently(): void {
		$versions = $this->versions(self::module('Polls', '1.1.0'));

		$this->installed->recordSchema('Polls', '1.1.0');
		$this->assertEquals(new InstalledVersion('1.1.0', '0'), $versions->installed('Polls'));
		$this->assertSame(array(false, true), array($versions->schemaBehind('Polls'), $versions->dataBehind('Polls')), 'a current schema with its data behind');

		$this->installed->recordData('Polls', '1.0.0');
		$this->assertEquals(new InstalledVersion('1.1.0', '1.0.0'), $versions->installed('Polls'));
		$this->assertSame(array('Polls'), $versions->behind());

		$this->installed->recordData('Polls', '1.1.0');
		$this->installed->recordSchema('Polls', '1.0.0');
		$this->assertSame(array(true, false), array($versions->schemaBehind('Polls'), $versions->dataBehind('Polls')), 'current data over a schema behind');
	}

	public function testTheModulesBehindAreListedInLoadOrderByPart(): void {
		$versions = $this->versions(self::module('Forums', '1.4.0'), self::module('Polls', '1.1.0'), self::module('Help', '2.0.0'));

		$this->installed->recordSchema('Forums', '1.4.0');
		$this->installed->recordData('Polls', '1.1.0');

		$this->assertSame(array('Polls', 'Help'), $versions->behindOnSchema());
		$this->assertSame(array('Forums', 'Help'), $versions->behindOnData());
		$this->assertSame(array('Forums', 'Polls', 'Help'), $versions->behind());
	}

	public function testAModuleIsRecordedAtTheVersionItDeclaresOnePartAtATime(): void {
		$versions = $this->versions(self::module('Polls', '1.1.0'));

		$versions->recordSchema('Polls');
		$this->assertEquals(new InstalledVersion('1.1.0', '0'), $versions->installed('Polls'));

		$versions->recordData('Polls');
		$this->assertEquals(new InstalledVersion('1.1.0', '1.1.0'), $versions->installed('Polls'));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Module Archive is not registered');
		$versions->recordSchema('Archive');
	}

	public function testAFreshInstallRecordsEveryModuleAtItsDeclaredVersion(): void {
		$versions = $this->versions(self::module('Forums', '1.4.0'), self::module('Polls', '2.1.0'));

		$versions->recordAll();

		$this->assertEquals(array('Forums' => new InstalledVersion('1.4.0', '1.4.0'), 'Polls' => new InstalledVersion('2.1.0', '2.1.0')), $this->installed->versions);
		$this->assertSame(array(), $versions->behind(), 'nothing to upgrade afterwards');
	}

	public function testVersionsCompareAsNumbersAndADowngradeIsNotBehind(): void {
		$versions = $this->versions(self::module('Polls', '1.10.0'), self::module('Forums', '1.4.0'));

		$this->installed->recordSchema('Polls', '1.9.0');
		$this->installed->recordSchema('Forums', '2.0.0');

		$this->assertSame(array(true, false), array($versions->schemaBehind('Polls'), $versions->schemaBehind('Forums')));
		$this->assertSame(array('Polls', 'Forums'), $versions->behind(), 'in load order, Forums for its data');
	}

	public function testAModuleTheBoardRecordsButNoLongerHasIsNotBehind(): void {
		$this->installed->recordSchema('Archive', '1.0.0');

		$versions = $this->versions(self::module('Polls', '1.0.0'));

		$this->assertSame(array('Polls' => '1.0.0'), $versions->declared());
		$this->assertSame(array(false, false), array($versions->schemaBehind('Archive'), $versions->dataBehind('Archive')));
		$this->assertSame(array('Polls'), $versions->behind());
	}

	/** Each module at the release its schema took its current shape in: 1.4.0's tables, 1.5.0's wider password and online ident, 2.0.0's own. */
	public function testEachForumModuleDeclaresTheReleaseItsSchemaFirstShippedIn(): void {
		$releases = array();
		foreach (ModuleRegistry::discover(ModuleTree::core(FORUM_ROOT))->modules() as $module)
			$releases[$module->version()][] = $module->name();

		ksort($releases);
		$releases = array_map(static function (array $modules): array { sort($modules); return $modules; }, $releases);

		$this->assertSame(array(
			'1.4.0' => array('Bans', 'Categories', 'Censoring', 'Extensions', 'Forums', 'Groups', 'Misc', 'Post', 'Ranks', 'Reports', 'Search', 'Settings'),
			'1.5.0' => array('Site'),
			'2.0.0' => array(
				'AdminIndex', 'Database', 'Delete', 'Edit', 'Extern', 'Framework', 'Help', 'Index', 'Install', 'Layout', 'LegacyBridge', 'Login',
				'Message', 'Moderate', 'Profile', 'Prune', 'Register', 'Reindex', 'Setup', 'Update', 'Userlist', 'Users', 'Viewforum', 'Viewtopic',
			),
		), $releases);
	}
}
