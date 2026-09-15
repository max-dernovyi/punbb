<?php
/**
 * The login page's repositories over an in-memory SQLite database with the
 * forum's tables: an account's credentials by its name in any case, what a
 * login and a password request store, and the visits a login and a logout end.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Login\Model\Accounts;
use PunBB\Module\Login\Model\Credentials;
use PunBB\Module\Login\Model\LastVisit;
use PunBB\Module\Login\Model\ResetKey;
use PunBB\Module\Login\Model\ResettableAccount;
use PunBB\Module\Login\Model\Visits;

class AccountsTest extends TestCase {
	private Connection $db;

	private Accounts $accounts;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');
		$this->db->execute('CREATE TABLE pun_users (id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL DEFAULT 3, username VARCHAR(200) NOT NULL, password VARCHAR(255) NOT NULL DEFAULT \'\', salt VARCHAR(12), email VARCHAR(80) NOT NULL, last_visit INTEGER NOT NULL DEFAULT 0, activate_key VARCHAR(8), last_email_sent INTEGER)');
		$this->db->execute('CREATE TABLE pun_online (user_id INTEGER NOT NULL, ident VARCHAR(200) NOT NULL)');
		$this->db->execute('INSERT INTO pun_users (id, group_id, username, password, salt, email, last_email_sent) VALUES (2, 1, ?, ?, NULL, ?, NULL), (5, 0, ?, ?, ?, ?, 1000), (6, 3, ?, ?, NULL, ?, NULL)',
			'Admin', 'hash-a', 'admin@example.com', 'Anna', 'hash-b', 'salt5', 'anna@example.com', 'Annie', 'hash-c', 'anna@example.com');
		$this->db->execute('INSERT INTO pun_online (user_id, ident) VALUES (1, ?), (1, ?), (5, ?)', '192.0.2.7', '192.0.2.8', 'Anna');

		$this->accounts = new Accounts($this->db);
	}

	public function testCredentialsAreFoundByTheNameInAnyCase(): void {
		$anna = $this->accounts->credentials('aNNa');

		$this->assertNotNull($anna);
		$this->assertSame(array(5, 0, 'hash-b', 'salt5'), array($anna->userId(), $anna->groupId(), $anna->passwordHash(), $anna->salt()));
		$this->assertSame('', $this->accounts->credentials('admin')?->salt());
		$this->assertNull($this->accounts->credentials('nobody'));
	}

	public function testALoginStoresThePasswordTheGroupAndALogoutTheLastVisit(): void {
		$this->accounts->storePassword(new Credentials(5, 0, 'hash-new', 'salt-new'));
		$this->accounts->activate(4, 5, 6);
		$this->accounts->recordLastVisit(new LastVisit(6, 12345));

		$this->assertSame(
			array(array(5, 4, 'hash-new', 'salt-new', 0), array(6, 4, 'hash-c', null, 12345)),
			array_map(static fn ($row): array => array($row->int('id'), $row->int('group_id'), $row->string('password'), $row->nullableString('salt'), $row->int('last_visit')),
				$this->db->select('SELECT id, group_id, password, salt, last_visit FROM pun_users WHERE id IN (5, 6) ORDER BY id'))
		);
	}

	public function testTheAccountsOfAnAddressGetTheirResetKeys(): void {
		$this->assertSame(array('5 0 Anna 1000', '6 3 Annie -'), array_map(static fn (ResettableAccount $account): string => $account->id().' '.$account->groupId().' '.$account->username().' '.($account->lastEmailSent() ?? '-'), $this->accounts->resettable('anna@example.com')));
		$this->assertSame(array(), $this->accounts->resettable('ANNA@example.com'));

		$this->accounts->issueResetKey(new ResetKey(6, 'k3yK3y00', 2000));

		$row = $this->db->selectRow('SELECT activate_key, last_email_sent FROM pun_users WHERE id=6');
		$this->assertSame(array('k3yK3y00', 2000), array($row?->string('activate_key'), $row?->int('last_email_sent')));
	}

	public function testALoginEndsTheGuestsVisitAndALogoutTheMembers(): void {
		$visits = new Visits($this->db);

		$visits->endGuestVisit('192.0.2.7');
		$visits->endVisit(5);

		$this->assertSame(array('192.0.2.8'), array_map(static fn ($row): string => $row->string('ident'), $this->db->select('SELECT ident FROM pun_online')));
	}
}
