<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Userlist;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Userlist\Api\Data\GroupInterface;
use PunBB\Module\Userlist\Api\Data\MemberInterface;
use PunBB\Module\Userlist\Api\Data\MemberSearchInterface;
use PunBB\Module\Userlist\Api\MemberDirectoryInterface;
use PunBB\Module\Userlist\Model\Group;
use PunBB\Module\Userlist\Model\Member;

/**
 * The member list's query points, after the directory has answered: each gets
 * the query array userlist.php built, and when extension code changed it, the
 * changed query is what answers, run through query_build() as it always was.
 */
final class MemberDirectoryPlugin {
	public function __construct(private readonly PluggedQuery $queries, private readonly MemberRows $rows) {}

	public function afterCount(MemberDirectoryInterface $subject, int $result, MemberSearchInterface $search): int {
		self::publish($search);

		$query = array(
			'SELECT'	=> 'COUNT(u.id)',
			'FROM'		=> 'users AS u',
			'WHERE'		=> self::where($search),
		);

		if ($this->queries->changed('ul_qr_get_user_count', MemberDirectoryInterface::class.'::count', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query));

		$page = self::page();
		$page['num_users'] = $result;
		$GLOBALS['forum_page'] = $page;

		return $result;
	}

	/**
	 * @param list<MemberInterface> $result
	 * @return list<MemberInterface>
	 */
	public function afterFind(MemberDirectoryInterface $subject, array $result, MemberSearchInterface $search, int $offset, int $limit): array {
		$query = array(
			'SELECT'	=> 'u.id, u.username, u.title, u.num_posts, u.registered, g.g_id, g.g_user_title',
			'FROM'		=> 'users AS u',
			'JOINS'		=> array(
				array(
					'LEFT JOIN'		=> 'groups AS g',
					'ON'			=> 'g.g_id=u.group_id'
				)
			),
			'WHERE'		=> self::where($search),
			'ORDER BY'	=> $search->sortBy().' '.($search->descending() ? 'DESC' : 'ASC').', u.id ASC',
			'LIMIT'		=> $offset.', '.$limit
		);

		$rows = array();
		if ($this->queries->changed('ul_qr_get_users', MemberDirectoryInterface::class.'::find', $query))
		{
			$rows = PluggedQuery::rows($query);

			$result = array();
			foreach ($rows as $row)
			{
				$result[] = new Member((int) Markers::markup($row['id'] ?? 0), Markers::markup($row['username'] ?? ''), Markers::markup($row['title'] ?? ''),
					(int) Markers::markup($row['num_posts'] ?? 0), (int) Markers::markup($row['registered'] ?? 0),
					isset($row['g_id']) ? (int) Markers::markup($row['g_id']) : null, isset($row['g_user_title']) ? Markers::markup($row['g_user_title']) : null);
			}
		}
		else
		{
			foreach ($result as $member)
				$rows[] = MemberRows::of($member);
		}

		foreach ($rows as $row)
			$this->rows->keep((int) Markers::markup($row['id'] ?? 0), $row);

		$GLOBALS['founded_user_datas'] = $rows;

		return $result;
	}

	/**
	 * @param list<GroupInterface> $result
	 * @return list<GroupInterface>
	 */
	public function afterGroups(MemberDirectoryInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'g.g_id, g.g_title',
			'FROM'		=> 'groups AS g',
			'WHERE'		=> 'g.g_id!='.Markers::markup(\FORUM_GUEST),
			'ORDER BY'	=> 'g.g_id'
		);

		if (!$this->queries->changed('ul_qr_get_groups', MemberDirectoryInterface::class.'::groups', $query))
			return $result;

		/** @var list<GroupInterface> $groups */
		$groups = array();
		foreach (PluggedQuery::rows($query) as $row)
			$groups[] = new Group((int) Markers::markup($row['g_id'] ?? 0), Markers::markup($row['g_title'] ?? ''));

		return $groups;
	}

	/** The list's WHERE clause, as userlist.php wrote it, with $where_sql and $like_command left where the page left them. */
	private static function where(MemberSearchInterface $search): string {
		$like_command = ($GLOBALS['db_type'] ?? null) === 'pgsql' ? 'ILIKE' : 'LIKE';

		$where_sql = array();
		if ($search->username() !== '')
			$where_sql[] = 'u.username '.$like_command.' \''.Markers::markup(LegacyConnection::legacy()->escape(str_replace('*', '%', $search->username()))).'\'';

		if ($search->groupId() > -1)
			$where_sql[] = 'u.group_id='.$search->groupId();

		$GLOBALS['like_command'] = $like_command;
		$GLOBALS['where_sql'] = $where_sql;

		return 'u.id > 1 AND u.group_id != '.Markers::markup(\FORUM_UNVERIFIED).($where_sql !== array() ? ' AND '.implode(' AND ', $where_sql) : '');
	}

	/** What userlist.php had put into $forum_page by the time it counted. */
	private static function publish(MemberSearchInterface $search): void {
		$page = self::page();
		$page['username'] = $search->username();
		$page['show_group'] = $search->groupId();
		$page['sort_by'] = $search->sortBy();
		$page['sort_dir'] = $search->descending() ? 'DESC' : 'ASC';
		$GLOBALS['forum_page'] = $page;
	}

	/** @return array<mixed> */
	private static function page(): array {
		return isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
	}
}
