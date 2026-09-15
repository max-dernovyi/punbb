<?php
/**
 * The registration page's repository over an in-memory SQLite database with
 * the forum's users table: the registrations from an address, the unverified
 * accounts pruned, and the accounts sharing an address.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Register\Model\Registrations;

class RegistrationsTest extends TestCase {
	private Connection $db;

	private Registrations $registrations;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');
		$this->db->execute('CREATE TABLE pun_users (id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL, username VARCHAR(200) NOT NULL, email VARCHAR(80) NOT NULL, registered INTEGER NOT NULL, registration_ip VARCHAR(39) NOT NULL, activate_key VARCHAR(8))');
		$this->db->execute('INSERT INTO pun_users (id, group_id, username, email, registered, registration_ip, activate_key) VALUES (2, 3, ?, ?, 100, ?, NULL), (3, 0, ?, ?, 200, ?, ?), (4, 0, ?, ?, 900, ?, ?), (5, 0, ?, ?, 100, ?, NULL)',
			'anna', 'anna@example.com', '192.0.2.7', 'stale', 'stale@example.com', '192.0.2.7', 'k3y', 'fresh', 'anna@example.com', '192.0.2.8', 'k3y', 'moved', 'moved@example.com', '192.0.2.9');

		$this->registrations = new Registrations($this->db);
	}

	public function testTheRegistrationsFromAnAddressAreCountedSinceAMoment(): void {
		$this->assertSame(2, $this->registrations->registrationsFrom('192.0.2.7', 99));
		$this->assertSame(1, $this->registrations->registrationsFrom('192.0.2.7', 100));
		$this->assertSame(0, $this->registrations->registrationsFrom('198.51.100.1', 0));
	}

	public function testOnlyAnAccountStillWaitingForItsKeyIsPruned(): void {
		$this->registrations->removeUnverified(500);

		$this->assertSame(array(2, 4, 5), array_map(static fn ($row): int => $row->int('id'), $this->db->select('SELECT id FROM pun_users ORDER BY id')));
	}

	public function testTheAccountsSharingAnAddressAreNamed(): void {
		$this->assertSame(array('anna', 'fresh'), $this->registrations->usernamesWithEmail('anna@example.com'));
		$this->assertSame(array(), $this->registrations->usernamesWithEmail('nobody@example.com'));
	}
}
