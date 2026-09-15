<?php
/**
 * The users page's repository over an in-memory SQLite database with the
 * forum's tables: the addresses a user posted from and who posted from one, a
 * search by every kind of criterion, and banning and moving the users selected.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Users\Api\Data\FoundUserInterface;
use PunBB\Module\Users\Api\Data\ListedGroupInterface;
use PunBB\Module\Users\Model\UserBan;
use PunBB\Module\Users\Model\Users;
use PunBB\Module\Users\Model\UserSearch;

class UsersTest extends TestCase {
	private Connection $db;

	private Users $users;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');
		$this->db->execute('CREATE TABLE pun_groups (g_id INTEGER PRIMARY KEY, g_title VARCHAR(50) NOT NULL DEFAULT \'\', g_user_title VARCHAR(50), g_moderator INTEGER NOT NULL DEFAULT 0)');
		$this->db->execute('CREATE TABLE pun_users (id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL DEFAULT 3, username VARCHAR(200) NOT NULL, email VARCHAR(80) NOT NULL DEFAULT \'\', title VARCHAR(50), realname VARCHAR(40), url VARCHAR(100), jabber VARCHAR(80), icq VARCHAR(12), msn VARCHAR(80), aim VARCHAR(30), yahoo VARCHAR(30), location VARCHAR(30), signature TEXT, admin_note VARCHAR(30), num_posts INTEGER NOT NULL DEFAULT 0, last_post INTEGER, registered INTEGER NOT NULL DEFAULT 0, registration_ip VARCHAR(39) NOT NULL DEFAULT \'0.0.0.0\')');
		$this->db->execute('CREATE TABLE pun_posts (id INTEGER PRIMARY KEY, poster VARCHAR(200) NOT NULL, poster_id INTEGER NOT NULL DEFAULT 1, poster_ip VARCHAR(39), posted INTEGER NOT NULL DEFAULT 0, topic_id INTEGER NOT NULL DEFAULT 1)');
		$this->db->execute('CREATE TABLE pun_bans (id INTEGER PRIMARY KEY, username VARCHAR(200), ip VARCHAR(255), email VARCHAR(80), message VARCHAR(255), expire INTEGER, ban_creator INTEGER NOT NULL DEFAULT 0)');

		$this->db->execute('INSERT INTO pun_groups (g_id, g_title, g_user_title, g_moderator) VALUES (1, ?, ?, 0), (2, ?, NULL, 0), (3, ?, NULL, 0), (4, ?, ?, 1)', 'Administrators', 'Administrator', 'Guest', 'Members', 'Moderators', 'Moderator');
		$this->db->execute('INSERT INTO pun_users (id, group_id, username, email, title, location, admin_note, num_posts, last_post, registered, registration_ip) VALUES'.
			' (1, 2, ?, \'\', NULL, NULL, NULL, 0, NULL, 0, ?),'.
			' (2, 1, ?, ?, NULL, ?, NULL, 12, 5000, 1000, ?),'.
			' (3, 3, ?, ?, ?, ?, ?, 3, 4000, 2000, ?),'.
			' (4, 0, ?, ?, NULL, NULL, NULL, 0, NULL, 3000, ?),'.
			' (5, 9, ?, ?, NULL, NULL, NULL, 7, 6000, 4000, ?),'.
			' (6, 4, ?, ?, NULL, NULL, NULL, 30, 7000, 5000, ?)',
			'Guest', '0.0.0.0', 'admin', 'admin@example.com', 'Paris', '192.0.2.1', 'member', 'member@example.com', 'Veteran', 'Paris', 'watch', '192.0.2.3',
			'newbie', 'new@example.com', '192.0.2.4', 'orphan', 'orphan@example.com', '192.0.2.5', 'mod', 'mod@example.com', '192.0.2.6');
		$this->db->execute('INSERT INTO pun_posts (id, poster, poster_id, poster_ip, posted) VALUES (1, ?, 3, ?, 100), (2, ?, 3, ?, 300), (3, ?, 3, ?, 200), (4, ?, 1, ?, 400), (5, ?, 6, ?, 500), (6, ?, 3, NULL, 50)',
			'member', '192.0.2.3', 'member', '192.0.2.33', 'member', '192.0.2.3', 'Visitor', '192.0.2.3', 'mod', '192.0.2.3', 'member');

		$this->users = new Users($this->db);
	}

	/**
	 * @param list<FoundUserInterface> $users
	 * @return list<string>
	 */
	private static function names(array $users): array {
		return array_map(static fn (FoundUserInterface $user): string => $user->username(), $users);
	}

	/** @param array<string, mixed> $query */
	private function search(array $query, string $orderBy = 'username', bool $descending = false): UserSearch {
		return UserSearch::fromQuery($query, $orderBy, $descending);
	}

	public function testTheAddressesAUserPostedFromComeTheOneUsedLastFirst(): void {
		$this->assertSame(array('192.0.2.33 300 1', '192.0.2.3 200 2', ' 50 1'), array_map(static fn ($use): string => $use->address().' '.$use->lastUsed().' '.$use->timesUsed(), $this->users->addressesOf(3)));
		$this->assertSame(array(), $this->users->addressesOf(4));
	}

	public function testThePostersFromAnAddressComeOnceEachByNameDescending(): void {
		$this->assertSame(array('6 mod', '3 member', '1 Visitor'), array_map(static fn ($poster): string => $poster->id().' '.$poster->name(), $this->users->postersFrom('192.0.2.3')));
		$this->assertSame(array(), $this->users->postersFrom('192.0.2.99'));
	}

	public function testAMemberIsReadWithTheirGroupButNotTheGuestAccountOrAUserWithoutAGroup(): void {
		$member = $this->users->member(3);

		$this->assertNotNull($member);
		$this->assertSame(array(3, 'member', 'member@example.com', 'Veteran', 3, 'watch', 3, null), array($member->id(), $member->username(), $member->email(), $member->title(), $member->postCount(), $member->adminNote(), $member->groupId(), $member->groupTitle()));
		$this->assertSame(array('', '', 'Administrator'), array($this->users->member(2)?->title(), $this->users->member(2)?->adminNote(), $this->users->member(2)?->groupTitle()));
		$this->assertNull($this->users->member(1));
		$this->assertNull($this->users->member(5));
		$this->assertNull($this->users->member(99));
	}

	public function testASearchMatchesEveryCriterionGivenAndNeverTheGuestAccount(): void {
		$paris = $this->search(array('form' => array('location' => 'par*')));
		$this->assertSame(2, $this->users->count($paris));
		$this->assertSame(array('admin', 'member'), self::names($this->users->find($paris, 0, 10)));

		$this->assertSame(array('member'), self::names($this->users->find($this->search(array('form' => array('location' => 'Paris', 'admin_note' => 'wat*'))), 0, 10)));
		$this->assertSame(array('admin', 'mod', 'orphan'), self::names($this->users->find($this->search(array('posts_greater' => '5')), 0, 10)));
		$this->assertSame(array('member', 'newbie'), self::names($this->users->find($this->search(array('posts_less' => '5')), 0, 10)));
		$this->assertSame(array('member', 'newbie', 'orphan'), self::names($this->users->find($this->search(array('registered_after' => '@1500', 'registered_before' => '@4500')), 0, 10)));
		$this->assertSame(array('admin', 'mod', 'orphan'), self::names($this->users->find($this->search(array('last_post_after' => '@4500')), 0, 10)));
		$this->assertSame(array('member'), self::names($this->users->find($this->search(array('last_post_before' => '@4500')), 0, 10)));
		$this->assertSame(array('newbie'), self::names($this->users->find($this->search(array('user_group' => '0')), 0, 10)));
		$this->assertSame(0, $this->users->count($this->search(array('form' => array('username' => 'Guest')))));
	}

	public function testASearchIsOrderedAndPaged(): void {
		$everyone = $this->search(array('posts_greater' => '0'), 'num_posts', true);

		$this->assertSame(array('mod', 'admin', 'orphan', 'member'), self::names($this->users->find($everyone, 0, 10)));
		$this->assertSame(array('orphan', 'member'), self::names($this->users->find($everyone, 2, 2)));
		$this->assertSame(array('member', 'admin', 'orphan', 'mod'), self::names($this->users->find($this->search(array('posts_greater' => '0'), 'last_post'), 0, 10)));
		$this->assertSame(array('admin', 'member', 'mod', 'orphan'), self::names($this->users->find($this->search(array('posts_greater' => '0'), 'email'), 0, 10)));

		$this->assertNull($this->users->find($this->search(array('user_group' => '0')), 0, 10)[0]->groupId(), 'no group row stands for the unverified');
		$this->assertNull($this->users->find($this->search(array('form' => array('username' => 'orphan'))), 0, 10)[0]->groupId(), 'a user whose group is gone is still found');
	}

	/** A column name reaches the SQL only from the lists the search was built against. */
	public function testASearchNamesNoColumnOutsideItsLists(): void {
		$hostile = $this->search(array('form' => array('password' => 'x', 'username) OR 1=1 --' => 'x', 'location' => 'par*')));

		$this->assertSame(array('location'), array_map(static fn ($field): string => $field->field(), $hostile->fields()));
		$this->assertSame(2, $this->users->count($hostile));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Users cannot be ordered by "password"');
		$this->search(array(), 'password');
	}

	public function testTheGroupsListedLeaveOutTheGuestsByTitle(): void {
		$listed = array('1 Administrators', '3 Members', '4 Moderators');

		$this->assertSame($listed, array_map(static fn (ListedGroupInterface $group): string => $group->id().' '.$group->title(), $this->users->searchGroups()));
		$this->assertSame($listed, array_map(static fn (ListedGroupInterface $group): string => $group->id().' '.$group->title(), $this->users->moveTargets()));
	}

	public function testTheSelectionIsCheckedForAdministrators(): void {
		$this->assertTrue($this->users->includesAdministrators(3, 2));
		$this->assertFalse($this->users->includesAdministrators(3, 4, 99));
		$this->assertFalse($this->users->includesAdministrators());
	}

	public function testUsersAreBannedFromWhatTheirAccountsAndPostsCarry(): void {
		$this->assertSame(array('3 192.0.2.3', '3 192.0.2.3', '3 192.0.2.33', '6 192.0.2.3'), array_map(static fn ($address): string => $address->userId().' '.$address->address(), array_slice($this->users->postAddresses(3, 6, 1), 1)));
		$this->assertSame('', $this->users->postAddresses(3)[0]->address(), 'a post that recorded no address comes first, being the oldest');
		$this->assertSame(array('3 member member@example.com 192.0.2.3', '4 newbie new@example.com 192.0.2.4'), array_map(static fn ($target): string => $target->id().' '.$target->username().' '.$target->email().' '.$target->registrationIp(), $this->users->banTargets(1, 3, 4)));

		$this->users->ban(new UserBan(3, 'member', '192.0.2.33', 'member@example.com', 'Go away', 9000, 2), new UserBan(4, 'new\'bie', '192.0.2.4', 'new@example.com', null, null, 2));

		$this->assertSame(array(
			array('username' => 'member', 'ip' => '192.0.2.33', 'email' => 'member@example.com', 'message' => 'Go away', 'expire' => 9000, 'ban_creator' => 2),
			array('username' => 'new\'bie', 'ip' => '192.0.2.4', 'email' => 'new@example.com', 'message' => null, 'expire' => null, 'ban_creator' => 2),
		), array_map(static fn ($row): array => $row->values(), $this->db->select('SELECT username, ip, email, message, expire, ban_creator FROM pun_bans ORDER BY id')));
	}

	public function testUsersMoveIntoAGroupButTheGuestAccountStays(): void {
		$this->assertTrue($this->users->groupModerates(4));
		$this->assertFalse($this->users->groupModerates(3));
		$this->assertNull($this->users->groupModerates(99));

		$this->users->moveToGroup(4, 1, 3, 4);
		$this->users->moveToGroup(3);

		$this->assertSame(array('1 2', '3 4', '4 4'), array_map(static fn ($row): string => $row->int('id').' '.$row->int('group_id'), $this->db->select('SELECT id, group_id FROM pun_users WHERE id IN (1, 3, 4) ORDER BY id')));
	}
}
