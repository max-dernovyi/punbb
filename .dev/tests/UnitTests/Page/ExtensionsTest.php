<?php
/**
 * The extensions page's repository over an in-memory SQLite database with the
 * forum's tables: the installed extensions and their dependencies, installing
 * and upgrading one with its hooks, uninstalling it and switching it.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Extensions\Api\Data\InstalledExtensionInterface;
use PunBB\Module\Extensions\Model\ExtensionRecord;
use PunBB\Module\Extensions\Model\ExtensionStatus;
use PunBB\Module\Extensions\Model\Extensions;
use PunBB\Module\Extensions\Model\HookRecord;

class ExtensionsTest extends TestCase {
	private Connection $db;

	private Extensions $extensions;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');
		$this->db->execute('CREATE TABLE pun_extensions (id VARCHAR(150) NOT NULL PRIMARY KEY, title VARCHAR(255) NOT NULL DEFAULT \'\', version VARCHAR(25) NOT NULL DEFAULT \'\', description TEXT, author VARCHAR(50) NOT NULL DEFAULT \'\', uninstall TEXT, uninstall_note TEXT, disabled TINYINT NOT NULL DEFAULT 0, dependencies VARCHAR(255) NOT NULL DEFAULT \'\')');
		$this->db->execute('CREATE TABLE pun_extension_hooks (id VARCHAR(150) NOT NULL, extension_id VARCHAR(50) NOT NULL, code TEXT, installed INTEGER NOT NULL, priority TINYINT NOT NULL DEFAULT 5)');
		$this->db->execute('INSERT INTO pun_extensions (id, title, version, description, author, uninstall, uninstall_note, disabled, dependencies) VALUES (?, ?, ?, ?, ?, NULL, NULL, 0, ?), (?, ?, ?, ?, ?, ?, ?, 1, ?)',
			'base', 'Base', '1.0', 'The base', 'Bob', '||',
			'top', 'Another', '2.0', '', 'Carl', 'drop();', 'Data goes', '|base|x_y|');

		$this->extensions = new Extensions($this->db);
	}

	/** @param list<InstalledExtensionInterface> $extensions */
	private static function listed(array $extensions): array {
		return array_map(static fn (InstalledExtensionInterface $e): string => $e->id().' '.$e->title().' '.$e->version().' "'.$e->uninstallCode().'" "'.$e->uninstallNote().'" '.implode(',', $e->dependencies()).($e->isDisabled() ? ' off' : ''), $extensions);
	}

	public function testTheExtensionsComeByTitleWithTheirDependencies(): void {
		$this->assertSame(array('top Another 2.0 "drop();" "Data goes" base,x_y off', 'base Base 1.0 "" "" '), self::listed($this->extensions->all()));
		$this->assertSame(array('base 1.0'), array_map(static fn ($v): string => $v->id().' '.$v->version(), $this->extensions->enabledVersions()));
		$this->assertSame(array('base'), $this->extensions->enabledIds());
		$this->assertSame('2.0', $this->extensions->installedVersion('top'));
		$this->assertNull($this->extensions->installedVersion('none'));
		$this->assertSame(array('base', 'x_y'), $this->extensions->dependencies('top'));
		$this->assertSame(array(), $this->extensions->dependencies('base'));
		$this->assertSame(array('top Another 2.0 "drop();" "Data goes" base,x_y off'), self::listed(array_filter(array($this->extensions->find('top')))));
		$this->assertNull($this->extensions->find('none'));
	}

	public function testWhatDependsOnAnExtensionIsFound(): void {
		$this->assertSame('top', $this->extensions->dependent('base'));
		$this->assertNull($this->extensions->enabledDependent('base'), 'the one depending on it is disabled');
		$this->assertNull($this->extensions->dependent('top'));

		$this->assertTrue($this->extensions->isDisabled('top'));
		$this->assertFalse($this->extensions->isDisabled('base'));
		$this->assertNull($this->extensions->isDisabled('none'));

		$this->extensions->setDisabled(new ExtensionStatus('top', false));
		$this->assertSame('top', $this->extensions->enabledDependent('base'));
	}

	public function testAnExtensionIsInstalledUpgradedAndUninstalledWithItsHooks(): void {
		$this->extensions->add(new ExtensionRecord('probe', 'Probe \'n\' "co"', '0.1', 'Probes', 'Anna', '', 'Keep it', array('base')));
		$this->extensions->addHooks(new HookRecord('in_start', 'probe', 'echo \'1\';', 1000, 3), new HookRecord('vt_start', 'probe', 'echo 2;', 1000, 5));

		$this->assertSame('probe Probe \'n\' "co" 0.1 "" "Keep it" base', self::listed(array_filter(array($this->extensions->find('probe'))))[0]);
		$this->assertSame(2, (int) $this->db->selectValue('SELECT COUNT(*) FROM pun_extension_hooks WHERE extension_id=?', 'probe'));

		$this->extensions->update(new ExtensionRecord('probe', 'Probe', '0.2', 'Probes more', 'Anna', 'undo();', '', array()));
		$this->extensions->clearHooks('probe');

		$this->assertSame('probe Probe 0.2 "undo();" "" ', self::listed(array_filter(array($this->extensions->find('probe'))))[0]);
		$this->assertSame('||', $this->db->selectValue('SELECT dependencies FROM pun_extensions WHERE id=?', 'probe'));
		$this->assertSame(0, (int) $this->db->selectValue('SELECT COUNT(*) FROM pun_extension_hooks'));

		$this->extensions->addHooks(new HookRecord('ft_end', 'probe', 'echo 3;', 1000, 5));
		$this->extensions->removeHooks('probe');
		$this->extensions->remove('probe');

		$this->assertNull($this->extensions->find('probe'));
		$this->assertSame(0, (int) $this->db->selectValue('SELECT COUNT(*) FROM pun_extension_hooks'));
	}
}
