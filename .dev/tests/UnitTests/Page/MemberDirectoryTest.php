<?php
/**
 * The member directory over an in-memory SQLite database with the forum's
 * users and groups tables: who counts as a member, the search's filters, the
 * order, the page, and the groups offered.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Userlist\Api\Data\MemberInterface;
use PunBB\Module\Userlist\Model\MemberDirectory;
use PunBB\Module\Userlist\Model\MemberSearch;

class MemberDirectoryTest extends TestCase {
	private MemberDirectory $directory;

	protected function setUp(): void {
		$db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');

		$db->execute('CREATE TABLE pun_groups (g_id INTEGER PRIMARY KEY, g_title VARCHAR(50) NOT NULL, g_user_title VARCHAR(50))');
		$db->execute('CREATE TABLE pun_users (id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL, username VARCHAR(200) NOT NULL, title VARCHAR(50), num_posts INTEGER NOT NULL, registered INTEGER NOT NULL)');

		foreach (array(array(0, 'Unverified', null), array(1, 'Administrators', 'Administrator'), array(2, 'Guests', null), array(3, 'Members', null), array(4, 'Moderators', 'Moderator')) as $group)
			$db->execute('INSERT INTO pun_groups (g_id, g_title, g_user_title) VALUES (?, ?, ?)', ...$group);

		foreach (array(
			array(1, 2, 'Guest', null, 0, 0),
			array(2, 1, 'admin', 'Boss', 10, 100),
			array(3, 3, 'Zoë', null, 5, 300),
			array(4, 4, 'mod', null, 5, 200),
			array(5, 0, 'pending', null, 0, 400),
			array(6, 3, 'Anna', null, 7, 500),
			array(7, 9, 'orphan', null, 1, 600),
		) as $user)
			$db->execute('INSERT INTO pun_users (id, group_id, username, title, num_posts, registered) VALUES (?, ?, ?, ?, ?, ?)', ...$user);

		$this->directory = new MemberDirectory($db);
	}

	/** @param list<MemberInterface> $members */
	private static function names(array $members): array {
		return array_map(static fn (MemberInterface $member): string => $member->username(), $members);
	}

	public function testTheGuestAndTheUnverifiedAreNotMembers(): void {
		$everyone = new MemberSearch('', -1, 'username', false);

		$this->assertSame(5, $this->directory->count($everyone));
		$this->assertSame(array('Anna', 'Zoë', 'admin', 'mod', 'orphan'), self::names($this->directory->find($everyone, 0, 50)));
	}

	public function testAMemberCarriesTheirGroupsTitleOrNothingWhenTheGroupIsGone(): void {
		$members = $this->directory->find(new MemberSearch('', -1, 'registered', false), 0, 50);

		$this->assertSame(array(2, 'admin', 'Boss', 10, 100, 1, 'Administrator'), array($members[0]->id(), $members[0]->username(), $members[0]->title(), $members[0]->postCount(), $members[0]->registered(), $members[0]->groupId(), $members[0]->groupTitle()));
		$this->assertSame(array('', 3, null), array($members[2]->title(), $members[2]->groupId(), $members[2]->groupTitle()));
		$this->assertSame(array('orphan', null, null), array($members[4]->username(), $members[4]->groupId(), $members[4]->groupTitle()));
	}

	public function testAUsernameMatchesWithAsteriskWildcardsRegardlessOfCase(): void {
		$search = new MemberSearch('A*', -1, 'username', false);

		$this->assertSame(2, $this->directory->count($search));
		$this->assertSame(array('Anna', 'admin'), self::names($this->directory->find($search, 0, 50)));
		$this->assertSame(0, $this->directory->count(new MemberSearch('an\'', -1, 'username', false)));
	}

	public function testAGroupNarrowsTheList(): void {
		$search = new MemberSearch('', 3, 'username', false);

		$this->assertSame(2, $this->directory->count($search));
		$this->assertSame(array('Anna', 'Zoë'), self::names($this->directory->find($search, 0, 50)));
		$this->assertSame(0, $this->directory->count(new MemberSearch('', 0, 'username', false)));
	}

	public function testTheListIsSortedThenOrderedByIdAndPaged(): void {
		$this->assertSame(array('orphan', 'Anna', 'Zoë', 'mod', 'admin'), self::names($this->directory->find(new MemberSearch('', -1, 'registered', true), 0, 50)));
		$this->assertSame(array('admin', 'Anna', 'Zoë', 'mod', 'orphan'), self::names($this->directory->find(new MemberSearch('', -1, 'num_posts', true), 0, 50)));
		$this->assertSame(array('Zoë', 'admin'), self::names($this->directory->find(new MemberSearch('', -1, 'username', false), 1, 2)));
		$this->assertSame(array(), $this->directory->find(new MemberSearch('', -1, 'username', false), 5, 2));
	}

	public function testEveryGroupButTheGuestsIsOffered(): void {
		$groups = array_map(static fn ($group): array => array($group->id(), $group->title()), $this->directory->groups());

		$this->assertSame(array(array(0, 'Unverified'), array(1, 'Administrators'), array(3, 'Members'), array(4, 'Moderators')), $groups);
	}
}
