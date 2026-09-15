<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Users;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Users\Api\Data\AddressUseInterface;
use PunBB\Module\Users\Api\Data\BanTargetInterface;
use PunBB\Module\Users\Api\Data\FoundUserInterface;
use PunBB\Module\Users\Api\Data\ListedGroupInterface;
use PunBB\Module\Users\Api\Data\PostAddressInterface;
use PunBB\Module\Users\Api\Data\PosterInterface;
use PunBB\Module\Users\Api\Data\UserBanInterface;
use PunBB\Module\Users\Api\Data\UserSearchInterface;
use PunBB\Module\Users\Api\UsersInterface;
use PunBB\Module\Users\Model\AddressUse;
use PunBB\Module\Users\Model\BanTarget;
use PunBB\Module\Users\Model\ListedGroup;
use PunBB\Module\Users\Model\PostAddress;
use PunBB\Module\Users\Model\Poster;

/**
 * The users page's query points, with the query arrays admin/users.php built.
 * A query a point changed answers instead, its rows kept with any column it
 * added; a statement a point changed runs instead, and the repository is
 * handed nothing to store. What was read is left where the page script kept
 * it: the addresses in $founded_ips, the posters in $users, their count in
 * $forum_page['num_users']; a search's $conditions and $like_command; the
 * latest addresses in $ips; a user banned in $cur_user and $ban_ip; the group
 * moved to in $group_is_mod.
 */
final class UsersPlugin {
	public function __construct(private readonly PluggedQuery $queries, private readonly UsersRows $rows, private readonly SelectedAction $action) {}

	/**
	 * @param list<AddressUseInterface> $result
	 * @return list<AddressUseInterface>
	 */
	public function afterAddressesOf(UsersInterface $subject, array $result, int $userId): array {
		$query = array(
			'SELECT'	=> 'p.poster_ip, MAX(p.posted) AS last_used, COUNT(p.id) AS used_times',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.poster_id='.$userId,
			'GROUP BY'	=> 'p.poster_ip',
			'ORDER BY'	=> 'last_used DESC'
		);

		if ($this->queries->changed('aus_ip_stats_qr_get_user_ips', UsersInterface::class.'::addressesOf', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$address = new AddressUse(Markers::markup($row['poster_ip'] ?? ''), (int) Markers::markup($row['last_used'] ?? 0), (int) Markers::markup($row['used_times'] ?? 0));
				$this->rows->keep($address, $row);
				$result[] = $address;
			}
		}

		$GLOBALS['founded_ips'] = array_map($this->rows->address(...), $result);
		ForumPage::set('num_users', count($result));

		return $result;
	}

	/**
	 * @param list<PosterInterface> $result
	 * @return list<PosterInterface>
	 */
	public function afterPostersFrom(UsersInterface $subject, array $result, string $address): array {
		$query = array(
			'SELECT'	=> 'DISTINCT p.poster_id, p.poster',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.poster_ip=\''.self::escape($address).'\'',
			'ORDER BY'	=> 'p.poster DESC'
		);

		if ($this->queries->changed('aus_show_users_qr_get_users_matching_ip', UsersInterface::class.'::postersFrom', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$poster = new Poster((int) Markers::markup($row['poster_id'] ?? 0), Markers::markup($row['poster'] ?? ''));
				$this->rows->keep($poster, $row);
				$result[] = $poster;
			}
		}

		$GLOBALS['users'] = array_map($this->rows->poster(...), $result);
		ForumPage::set('num_users', count($result));

		return $result;
	}

	public function afterMember(UsersInterface $subject, ?FoundUserInterface $result, int $id): ?FoundUserInterface {
		$query = array(
			'SELECT'	=> 'u.id, u.username, u.email, u.title, u.num_posts, u.admin_note, g.g_id, g.g_user_title',
			'FROM'		=> 'users AS u',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'groups AS g',
					'ON'			=> 'g.g_id=u.group_id'
				)
			),
			'WHERE'		=> 'u.id>1 AND u.id='.$id
		);

		if ($this->queries->changed('aus_show_users_qr_get_user_details', UsersInterface::class.'::member', $query))
		{
			$result = null;
			foreach (array_slice(PluggedQuery::rows($query), 0, 1) as $row)
			{
				$result = UsersRows::userOf($row);
				$this->rows->keep($result, $row);
			}
		}

		return $result;
	}

	public function afterCount(UsersInterface $subject, int $result, UserSearchInterface $search): int {
		$query = array(
			'SELECT'	=> 'COUNT(id)',
			'FROM'		=> 'users AS u',
			'JOINS'		=> array(
				array(
					'LEFT JOIN'		=> 'groups AS g',
					'ON'			=> 'g.g_id=u.group_id'
				)
			),
			'WHERE'		=> 'u.id>1 AND '.implode(' AND ', self::conditions($search))
		);

		if ($this->queries->changed('aus_find_user_qr_count_find_users', UsersInterface::class.'::count', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query));

		ForumPage::set('num_users', $result);

		return $result;
	}

	/**
	 * @param list<FoundUserInterface> $result
	 * @return list<FoundUserInterface>
	 */
	public function afterFind(UsersInterface $subject, array $result, UserSearchInterface $search, int $offset, int $limit): array {
		$query = array(
			'SELECT'	=> 'u.id, u.username, u.email, u.title, u.num_posts, u.admin_note, g.g_id, g.g_user_title',
			'FROM'		=> 'users AS u',
			'JOINS'		=> array(
				array(
					'LEFT JOIN'		=> 'groups AS g',
					'ON'			=> 'g.g_id=u.group_id'
				)
			),
			'WHERE'		=> 'u.id>1 AND '.implode(' AND ', self::conditions($search)),
			'ORDER BY'	=> $search->orderBy().' '.($search->descending() ? 'DESC' : 'ASC'),
			'LIMIT'		=> $offset.', '.$limit
		);

		ForumPage::set('start_from', $offset);
		ForumPage::set('finish_at', $limit);

		if ($this->queries->changed('aus_find_user_qr_find_users', UsersInterface::class.'::find', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$user = UsersRows::userOf($row);
				$this->rows->keep($user, $row);
				$result[] = $user;
			}
		}

		return $result;
	}

	/**
	 * @param list<ListedGroupInterface> $result
	 * @return list<ListedGroupInterface>
	 */
	public function afterSearchGroups(UsersInterface $subject, array $result): array {
		return $this->groups('aus_search_form_qr_get_groups', 'searchGroups', $result);
	}

	public function afterIncludesAdministrators(UsersInterface $subject, bool $result, int ...$ids): bool {
		$point = $this->action->checkPoint();
		if ($point === '')
			return $result;

		$query = array(
			'SELECT'	=> 'COUNT(u.id)',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.id IN ('.implode(',', $ids).') AND u.group_id='.ListedGroupInterface::ADMINISTRATORS
		);

		if ($this->queries->changed($point, UsersInterface::class.'::includesAdministrators', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query)) > 0;

		return $result;
	}

	/**
	 * @param list<PostAddressInterface> $result
	 * @return list<PostAddressInterface>
	 */
	public function afterPostAddresses(UsersInterface $subject, array $result, int ...$ids): array {
		$query = array(
			'SELECT'	=> 'p.poster_id, p.poster_ip',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.poster_id IN ('.implode(',', $ids).') AND p.poster_id>1',
			'ORDER BY'	=> 'p.posted ASC'
		);

		if ($this->queries->changed('aus_ban_users_qr_get_latest_user_ips', UsersInterface::class.'::postAddresses', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
				$result[] = new PostAddress((int) Markers::markup($row['poster_id'] ?? 0), Markers::markup($row['poster_ip'] ?? ''));
		}

		$ips = array();
		foreach ($result as $address)
			$ips[$address->userId()] = $address->address();

		$GLOBALS['ips'] = $ips;

		return $result;
	}

	/**
	 * @param list<BanTargetInterface> $result
	 * @return list<BanTargetInterface>
	 */
	public function afterBanTargets(UsersInterface $subject, array $result, int ...$ids): array {
		$query = array(
			'SELECT'	=> 'u.id, u.username, u.email, u.registration_ip',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'id IN ('.implode(',', $ids).') AND id>1'
		);

		if ($this->queries->changed('aus_ban_users_qr_get_users', UsersInterface::class.'::banTargets', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$target = new BanTarget((int) Markers::markup($row['id'] ?? 0), Markers::markup($row['username'] ?? ''), Markers::markup($row['email'] ?? ''), Markers::markup($row['registration_ip'] ?? ''));
				$this->rows->keep($target, $row);
				$result[] = $target;
			}
		}
		else
		{
			foreach ($result as $target)
				$this->rows->keep($target, $this->rows->target($target->id(), $target));
		}

		return $result;
	}

	/** @return list<UserBanInterface>|null */
	public function beforeBan(UsersInterface $subject, UserBanInterface ...$bans): ?array {
		$kept = array();
		foreach ($bans as $ban)
		{
			$GLOBALS['cur_user'] = $this->rows->target($ban->userId());
			$GLOBALS['ban_ip'] = $ban->ip();

			$query = array(
				'INSERT'	=> 'username, ip, email, message, expire, ban_creator',
				'INTO'		=> 'bans',
				'VALUES'	=> '\''.self::escape($ban->username()).'\', \''.self::escape($ban->ip()).'\', \''.self::escape($ban->email()).'\', '.BanUsersStepObserver::message($ban->message() ?? '').', '.($ban->expire() ?? 'NULL').', '.$ban->creatorId()
			);

			if ($this->queries->changed('aus_ban_users_qr_add_ban', UsersInterface::class.'::ban', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $ban;
		}

		return count($kept) !== count($bans) ? $kept : null;
	}

	public function afterGroupModerates(UsersInterface $subject, ?bool $result, int $id): ?bool {
		$query = array(
			'SELECT'	=> 'g.g_moderator',
			'FROM'		=> 'groups AS g',
			'WHERE'		=> 'g.g_id='.$id
		);

		if ($this->queries->changed('aus_change_group_qr_get_group_moderator_status', UsersInterface::class.'::groupModerates', $query))
		{
			$value = PluggedQuery::value($query);
			$result = $value !== null && $value !== false ? Markers::markup($value) !== '0' : null;
		}

		$GLOBALS['group_is_mod'] = $result !== null ? ($result ? '1' : '0') : false;

		return $result;
	}

	/** @return list<int>|null */
	public function beforeMoveToGroup(UsersInterface $subject, int $groupId, int ...$ids): ?array {
		$query = array(
			'UPDATE'	=> 'users',
			'SET'		=> 'group_id='.$groupId,
			'WHERE'		=> 'id IN ('.implode(',', $ids).') AND id>1'
		);

		if (!$this->queries->changed('aus_change_group_qr_change_user_group', UsersInterface::class.'::moveToGroup', $query))
			return null;

		PluggedQuery::run($query);

		return array($groupId);
	}

	/**
	 * @param list<ListedGroupInterface> $result
	 * @return list<ListedGroupInterface>
	 */
	public function afterMoveTargets(UsersInterface $subject, array $result): array {
		return $this->groups('aus_change_group_qr_get_groups', 'moveTargets', $result);
	}

	/**
	 * @param list<ListedGroupInterface> $result
	 * @return list<ListedGroupInterface>
	 */
	private function groups(string $point, string $method, array $result): array {
		$query = array(
			'SELECT'	=> 'g.g_id, g.g_title',
			'FROM'		=> 'groups AS g',
			'WHERE'		=> 'g.g_id!='.ListedGroupInterface::GUESTS,
			'ORDER BY'	=> 'g.g_title'
		);

		if ($this->queries->changed($point, UsersInterface::class.'::'.$method, $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
				$result[] = new ListedGroup((int) Markers::markup($row['g_id'] ?? 0), Markers::markup($row['g_title'] ?? ''));
		}

		return $result;
	}

	/**
	 * A search's conditions as admin/users.php wrote them, left in $conditions
	 * with $like_command and $user_group.
	 *
	 * @return list<string>
	 */
	private static function conditions(UserSearchInterface $search): array {
		$like_command = ($GLOBALS['db_type'] ?? null) === 'pgsql' ? 'ILIKE' : 'LIKE';

		$conditions = array();
		foreach (array('u.last_post>' => $search->lastPostAfter(), 'u.last_post<' => $search->lastPostBefore(), 'u.registered>' => $search->registeredAfter(), 'u.registered<' => $search->registeredBefore()) as $condition => $bound)
			if ($bound !== null)
				$conditions[] = $condition.$bound;

		foreach ($search->fields() as $field)
			$conditions[] = 'u.'.self::escape($field->field()).' '.$like_command.' \''.self::escape(str_replace('*', '%', $field->text())).'\'';

		foreach (array('u.num_posts>' => $search->postsMoreThan(), 'u.num_posts<' => $search->postsLessThan()) as $condition => $bound)
			if ($bound !== null)
				$conditions[] = $condition.$bound;

		if ($search->groupId() > -1)
			$conditions[] = 'u.group_id='.$search->groupId();

		$GLOBALS['conditions'] = $conditions;
		$GLOBALS['like_command'] = $like_command;
		$GLOBALS['user_group'] = $search->groupId();

		return $conditions;
	}

	private static function escape(string $text): string {
		return Markers::markup(LegacyConnection::legacy()->escape($text));
	}
}
