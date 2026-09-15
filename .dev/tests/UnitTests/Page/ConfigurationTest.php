<?php
/**
 * The settings page's repository over an in-memory SQLite database with the
 * config table: a permission and an option stored under their names, NULL
 * for an empty option, and nothing else touched.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Settings\Model\Configuration;
use PunBB\Module\Settings\Model\Setting;

class ConfigurationTest extends TestCase {
	private Connection $db;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');
		$this->db->execute('CREATE TABLE pun_config (conf_name VARCHAR(255) NOT NULL PRIMARY KEY, conf_value TEXT)');
		$this->db->execute('INSERT INTO pun_config (conf_name, conf_value) VALUES (?, ?), (?, ?), (?, ?), (?, ?)',
			'o_board_title', 'Board', 'o_smtp_host', 'mail.example.com', 'p_sig_length', '400', 'o_board_desc', 'Unchanged');
	}

	/** @return array<string, string|null> */
	private function stored(): array {
		$stored = array();
		foreach ($this->db->select('SELECT conf_name, conf_value FROM '.$this->db->table('config').' ORDER BY conf_name') as $row)
			$stored[$row->string('conf_name')] = $row->nullableString('conf_value');

		return $stored;
	}

	public function testEachSettingIsStoredUnderItsName(): void {
		$configuration = new Configuration($this->db);

		$configuration->updatePermissions(new Setting('p_sig_length', '250'));
		$configuration->updateOptions(new Setting('o_board_title', 'Board\'s "new" title'), new Setting('o_smtp_host', null));

		$this->assertSame(array('o_board_desc' => 'Unchanged', 'o_board_title' => 'Board\'s "new" title', 'o_smtp_host' => null, 'p_sig_length' => '250'), $this->stored());
	}

	public function testNoSettingsStoreNothing(): void {
		$before = $this->stored();

		(new Configuration($this->db))->updateOptions();

		$this->assertSame($before, $this->stored());
	}
}
