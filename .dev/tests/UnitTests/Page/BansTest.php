<?php
/**
 * The bans page's repositories over an in-memory SQLite database with the
 * forum's tables: the bans a page at a time with who created them, a ban,
 * adding, updating and removing bans, and the member a ban is made for with
 * the address they last posted from.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Bans\Api\Data\BanInterface;
use PunBB\Module\Bans\Model\Ban;
use PunBB\Module\Bans\Model\BanCandidates;
use PunBB\Module\Bans\Model\Bans;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;

class BansTest extends TestCase {
	private Bans $bans;

	private BanCandidates $candidates;

	protected function setUp(): void {
		$db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');

		foreach (array(
			'CREATE TABLE pun_bans (id INTEGER PRIMARY KEY, username VARCHAR(200), ip VARCHAR(255), email VARCHAR(80), message VARCHAR(255), expire INTEGER, ban_creator INTEGER NOT NULL DEFAULT 0)',
			'CREATE TABLE pun_users (id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL, username VARCHAR(200) NOT NULL, email VARCHAR(80) NOT NULL, registration_ip VARCHAR(39) NOT NULL)',
			'CREATE TABLE pun_posts (id INTEGER PRIMARY KEY, poster_id INTEGER NOT NULL, poster_ip VARCHAR(39), posted INTEGER NOT NULL)',
		) as $table)
			$db->execute($table);

		$db->execute('INSERT INTO pun_users (id, group_id, username, email, registration_ip) VALUES (1, 2, ?, ?, ?), (2, 1, ?, ?, ?), (3, 3, ?, ?, ?)',
			'Guest', '', '0.0.0.0', 'admin', 'admin@example.com', '192.0.2.1', 'anna', 'anna@example.com', '192.0.2.3');
		$db->execute('INSERT INTO pun_bans (id, username, ip, email, message, expire, ban_creator) VALUES (1, ?, NULL, NULL, ?, NULL, 2), (2, NULL, ?, ?, NULL, 2000, 9), (3, ?, NULL, NULL, NULL, NULL, 2)',
			'spammer', 'Go away', '198.51.100.7', 'banned.invalid', 'troll');
		$db->execute('INSERT INTO pun_posts (id, poster_id, poster_ip, posted) VALUES (1, 3, ?, 100), (2, 3, ?, 300), (3, 3, ?, 200)', '192.0.2.10', '192.0.2.30', '192.0.2.20');

		$this->bans = new Bans($db);
		$this->candidates = new BanCandidates($db);
	}

	private static function described(?BanInterface $ban): string {
		return $ban === null ? 'none' : implode('|', array($ban->id(), var_export($ban->username(), true), var_export($ban->ip(), true), var_export($ban->email(), true),
			var_export($ban->message(), true), var_export($ban->expire(), true), $ban->creatorId(), var_export($ban->creatorName(), true)));
	}

	public function testTheBansComeAPageAtATimeWithWhoCreatedThem(): void {
		$this->assertSame(3, $this->bans->count());
		$this->assertSame(array("1|'spammer'|NULL|NULL|'Go away'|NULL|2|'admin'", "2|NULL|'198.51.100.7'|'banned.invalid'|NULL|2000|9|NULL"),
			array_map(self::described(...), $this->bans->page(0, 2)));
		$this->assertSame(array("3|'troll'|NULL|NULL|NULL|NULL|2|'admin'"), array_map(self::described(...), $this->bans->page(2, 2)));
		$this->assertSame("2|NULL|'198.51.100.7'|'banned.invalid'|NULL|2000|9|NULL", self::described($this->bans->find(2)));
		$this->assertSame('none', self::described($this->bans->find(7)));
	}

	public function testBansAreAddedUpdatedAndRemovedAndAnUpdateKeepsTheirCreator(): void {
		$this->bans->add(new Ban(99, 'eve', null, 'eve@example.com', null, 5000, 3));
		$this->bans->update(new Ban(1, null, '203.0.113.1', null, 'Changed', null, 3));
		$this->bans->remove(3);

		$this->assertSame(array("1|NULL|'203.0.113.1'|NULL|'Changed'|NULL|2|'admin'", "2|NULL|'198.51.100.7'|'banned.invalid'|NULL|2000|9|NULL", "4|'eve'|NULL|'eve@example.com'|NULL|5000|3|'anna'"),
			array_map(self::described(...), $this->bans->page(0, 10)));
	}

	public function testAMemberIsFoundByIdOrByNameButNeverAsTheGuest(): void {
		$anna = $this->candidates->byId(3);
		$this->assertSame(array(3, 3, 'anna', 'anna@example.com', '192.0.2.3', false), array($anna?->id(), $anna?->groupId(), $anna?->username(), $anna?->email(), $anna?->registrationIp(), $anna?->isAdministrator()));
		$this->assertTrue($this->candidates->byUsername('admin')?->isAdministrator());
		$this->assertNull($this->candidates->byUsername('Guest'));
		$this->assertNull($this->candidates->byId(9));
	}

	public function testTheLastKnownAddressIsTheLatestPostsOne(): void {
		$this->assertSame('192.0.2.30', $this->candidates->lastKnownIp(3));
		$this->assertNull($this->candidates->lastKnownIp(2));
	}
}
