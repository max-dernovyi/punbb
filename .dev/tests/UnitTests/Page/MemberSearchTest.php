<?php
/**
 * What a member list request asks for: the search it reads from the query,
 * userlist.php:35's group check among it, and the page it starts on.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Userlist\Model\MemberSearch;
use PunBB\Module\Userlist\View\Pagination;

class MemberSearchTest extends TestCase {
	private static function search(array $query, bool $searchesUsernames = true, bool $showsPostCount = true): array {
		$search = MemberSearch::fromQuery($query, $searchesUsernames, $showsPostCount);

		return array($search->username(), $search->groupId(), $search->sortBy(), $search->descending());
	}

	public function testAnEmptyQueryListsEveryoneByUsernameAscending(): void {
		$this->assertSame(array('', -1, 'username', false), self::search(array()));
	}

	/** userlist.php:35 checked the group with a condition no integer satisfies. */
	public static function groupProvider(): array {
		return array(
			'every group'		=> array('-1', -1),
			'below every group'	=> array('-2', -1),
			'far below'			=> array('-999', -1),
			'the admins'		=> array('1', 1),
			'a custom group'	=> array('4', 4),
			'not a number'		=> array('mods', 0),
			'an array'			=> array(array('4'), -1),
		);
	}

	#[DataProvider('groupProvider')]
	public function testAGroupBelowEveryGroupIsEveryGroup(string|array $group, int $expected): void {
		$this->assertSame($expected, self::search(array('show_group' => $group))[1]);
	}

	public function testAUsernameCountsOnlyForAVisitorWhoMaySearchUsernames(): void {
		$this->assertSame('pc-user-4*', self::search(array('username' => 'pc-user-4*'))[0]);
		$this->assertSame('', self::search(array('username' => 'pc-user-4*'), false)[0]);
		$this->assertSame('', self::search(array('username' => '-'))[0]);
		$this->assertSame('', self::search(array('username' => array('x')))[0]);
	}

	public function testThePostCountSortsOnlyAListShowingIt(): void {
		$this->assertSame('num_posts', self::search(array('sort_by' => 'num_posts'))[2]);
		$this->assertSame('username', self::search(array('sort_by' => 'num_posts'), true, false)[2]);
		$this->assertSame('registered', self::search(array('sort_by' => 'registered'), true, false)[2]);
		$this->assertSame('username', self::search(array('sort_by' => 'email'))[2]);
		$this->assertSame('username', self::search(array('sort_by' => array('registered')))[2]);
	}

	public function testOnlyDescInAnyCaseSortsDescending(): void {
		$this->assertTrue(self::search(array('sort_dir' => 'desc'))[3]);
		$this->assertFalse(self::search(array('sort_dir' => 'DOWN'))[3]);
		$this->assertFalse(self::search(array('sort_dir' => array('DESC')))[3]);
	}

	public function testASortThereIsNoColumnForIsRefused(): void {
		$this->expectException(InvalidArgumentException::class);
		new MemberSearch('', -1, 'password', false);
	}

	/** @return array<string, array{mixed, int, int, int}> */
	public static function pageProvider(): array {
		return array(
			'absent'			=> array(null, 1, 0, 50),
			'the second'		=> array('2', 2, 50, 50),
			'fractional'		=> array('1.9', 1, 0, 50),
			'past the last by a fraction'	=> array('2.5', 1, 0, 50),
			'past the last'		=> array('3', 1, 0, 50),
			'the first'			=> array('1', 1, 0, 50),
			'negative'			=> array('-3', 1, 0, 50),
			'not a number'		=> array('two', 1, 0, 50),
			'an array'			=> array(array('2'), 1, 0, 50),
		);
	}

	#[DataProvider('pageProvider')]
	public function testAPageThatDoesNotExistIsTheFirst(mixed $requested, int $page, int $offset, int $last): void {
		$pagination = Pagination::of(51, $requested, 50);

		$this->assertSame(array(2, $page, $offset, $last), array($pagination->pages, $pagination->page, $pagination->offset, $pagination->lastOnPage));
	}

	public function testAnEmptyListingHasNoPagesAndStartsOnTheFirst(): void {
		$pagination = Pagination::of(0, '2', 50);

		$this->assertSame(array(0, 1, 0, 0), array($pagination->pages, $pagination->page, $pagination->offset, $pagination->lastOnPage));
	}
}
