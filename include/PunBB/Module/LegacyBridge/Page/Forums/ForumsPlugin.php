<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Forums;

use PunBB\Module\Forums\Api\Data\CategoryInterface;
use PunBB\Module\Forums\Api\Data\ForumInterface;
use PunBB\Module\Forums\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Forums\Api\Data\ForumPositionInterface;
use PunBB\Module\Forums\Api\Data\GroupDefaultsInterface;
use PunBB\Module\Forums\Api\Data\GroupPermissionsInterface;
use PunBB\Module\Forums\Api\Data\ListedForumInterface;
use PunBB\Module\Forums\Api\ForumsInterface;
use PunBB\Module\Forums\Model\Category;
use PunBB\Module\Forums\Model\Forum;
use PunBB\Module\Forums\Model\ForumPosition;
use PunBB\Module\Forums\Model\GroupDefaults;
use PunBB\Module\Forums\Model\GroupPermissions;
use PunBB\Module\Forums\Model\ListedForum;
use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\FoundName;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;

/**
 * The forums page's query points, with the query arrays admin/forums.php
 * built. A query a point changed answers instead, its rows kept with any
 * column it added; a statement a point changed runs instead, and the
 * repository is handed nothing to store. What was read is left where the page
 * script kept it: the forums listed in $forums, the edited forum in $cur_forum,
 * a deleted forum's name in $forum_name.
 */
final class ForumsPlugin {
	/** @var array<int, int> forum id => the position the forum had, as the positions were read */
	private array $storedPositions = array();

	public function __construct(private readonly PluggedQuery $queries, private readonly ForumsRows $rows) {}

	/**
	 * @param list<ListedForumInterface> $result
	 * @return list<ListedForumInterface>
	 */
	public function afterAll(ForumsInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.disp_position',
			'FROM'		=> 'categories AS c',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'c.id=f.cat_id'
				)
			),
			'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
		);

		if ($this->queries->changed('afo_qr_get_cats_and_forums', ForumsInterface::class.'::all', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$forum = new ListedForum((int) Markers::markup($row['cid'] ?? 0), Markers::markup($row['cat_name'] ?? ''), (int) Markers::markup($row['fid'] ?? 0), Markers::markup($row['forum_name'] ?? ''), (int) Markers::markup($row['disp_position'] ?? 0));
				$this->rows->keep($forum, $row);
				$result[] = $forum;
			}
		}

		$GLOBALS['forums'] = array_map($this->rows->listed(...), $result);

		return $result;
	}

	/**
	 * @param list<CategoryInterface> $result
	 * @return list<CategoryInterface>
	 */
	public function afterCategories(ForumsInterface $subject, array $result): array {
		return $this->categories('afo_qr_get_categories', 'categories', $result);
	}

	/**
	 * @param list<CategoryInterface> $result
	 * @return list<CategoryInterface>
	 */
	public function afterAssignableCategories(ForumsInterface $subject, array $result): array {
		return $this->categories('afo_edit_forum_qr_get_categories', 'assignableCategories', $result);
	}

	public function afterCategoryExists(ForumsInterface $subject, bool $result, int $id): bool {
		$query = array(
			'SELECT'	=> 'COUNT(c.id)',
			'FROM'		=> 'categories AS c',
			'WHERE'		=> 'c.id='.$id
		);

		if ($this->queries->changed('afo_add_forum_qr_validate_category_id', ForumsInterface::class.'::categoryExists', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query)) === 1;

		return $result;
	}

	/** @return list<ForumInterface>|null */
	public function beforeAdd(ForumsInterface $subject, ForumInterface ...$forums): ?array {
		$kept = array();
		foreach ($forums as $forum)
		{
			$query = array(
				'INSERT'	=> 'forum_name, disp_position, cat_id',
				'INTO'		=> 'forums',
				'VALUES'	=> '\''.self::escape($forum->name()).'\', '.$forum->position().', '.$forum->categoryId()
			);

			if ($this->queries->changed('afo_add_forum_qr_add_forum', ForumsInterface::class.'::add', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $forum;
		}

		return count($kept) !== count($forums) ? $kept : null;
	}

	public function afterFind(ForumsInterface $subject, ?ForumInterface $result, int $id): ?ForumInterface {
		$query = array(
			'SELECT'	=> 'f.id, f.forum_name, f.forum_desc, f.redirect_url, f.num_topics, f.sort_by, f.cat_id',
			'FROM'		=> 'forums AS f',
			'WHERE'		=> 'f.id='.$id
		);

		if ($this->queries->changed('afo_edit_forum_qr_get_forum_details', ForumsInterface::class.'::find', $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$result = null;
			if ($row !== null)
			{
				$result = new Forum(
					(int) Markers::markup($row['id'] ?? 0),
					Markers::markup($row['forum_name'] ?? ''),
					isset($row['forum_desc']) ? Markers::markup($row['forum_desc']) : null,
					isset($row['redirect_url']) ? Markers::markup($row['redirect_url']) : null,
					(int) Markers::markup($row['sort_by'] ?? 0),
					(int) Markers::markup($row['cat_id'] ?? 0),
					0,
					(int) Markers::markup($row['num_topics'] ?? 0)
				);
				$this->rows->keep($result, $row);
			}
		}

		$GLOBALS['cur_forum'] = $result !== null ? $this->rows->edited($result) : false;

		return $result;
	}

	public function afterName(ForumsInterface $subject, ?string $result, int $id): ?string {
		$query = array(
			'SELECT'	=> 'f.forum_name',
			'FROM'		=> 'forums AS f',
			'WHERE'		=> 'f.id='.$id
		);

		if ($this->queries->changed('afo_del_forum_qr_get_forum_name', ForumsInterface::class.'::name', $query))
			$result = FoundName::of($query);

		$GLOBALS['forum_name'] = $result;

		return $result;
	}

	/** @return list<ForumInterface>|null */
	public function beforeUpdate(ForumsInterface $subject, ForumInterface ...$forums): ?array {
		$kept = array();
		foreach ($forums as $forum)
		{
			$description = $forum->description() !== null ? '\''.self::escape($forum->description()).'\'' : 'NULL';
			$redirectUrl = $forum->redirectUrl() !== null ? '\''.self::escape($forum->redirectUrl()).'\'' : 'NULL';

			$GLOBALS['forum_desc'] = $description;
			$GLOBALS['redirect_url'] = $redirectUrl;

			$query = array(
				'UPDATE'	=> 'forums',
				'SET'		=> 'forum_name=\''.self::escape($forum->name()).'\', forum_desc='.$description.', redirect_url='.$redirectUrl.', sort_by='.$forum->sortBy().', cat_id='.$forum->categoryId(),
				'WHERE'		=> 'id='.$forum->id()
			);

			if ($this->queries->changed('afo_save_forum_qr_update_forum', ForumsInterface::class.'::update', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $forum;
		}

		return count($kept) !== count($forums) ? $kept : null;
	}

	/**
	 * @param list<ForumPositionInterface> $result
	 * @return list<ForumPositionInterface>
	 */
	public function afterPositions(ForumsInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'f.id, f.disp_position',
			'FROM'		=> 'categories AS c',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'c.id=f.cat_id'
				)
			),
			'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
		);

		if ($this->queries->changed('afo_update_positions_qr_get_forums', ForumsInterface::class.'::positions', $query))
			$result = array_map(static fn (array $row): ForumPosition => new ForumPosition((int) Markers::markup($row['id'] ?? 0), (int) Markers::markup($row['disp_position'] ?? 0)), PluggedQuery::rows($query));

		$this->storedPositions = array();
		foreach ($result as $position)
			$this->storedPositions[$position->forumId()] = $position->position();

		return $result;
	}

	/** @return list<ForumPositionInterface>|null */
	public function beforeReposition(ForumsInterface $subject, ForumPositionInterface ...$positions): ?array {
		$kept = array();
		foreach ($positions as $position)
		{
			$GLOBALS['cur_forum'] = array('id' => $position->forumId(), 'disp_position' => $this->storedPositions[$position->forumId()] ?? null);
			$GLOBALS['new_disp_position'] = $position->position();

			$query = array(
				'UPDATE'	=> 'forums',
				'SET'		=> 'disp_position='.$position->position(),
				'WHERE'		=> 'id='.$position->forumId()
			);

			if ($this->queries->changed('afo_update_positions_qr_update_forum_position', ForumsInterface::class.'::reposition', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $position;
		}

		return count($kept) !== count($positions) ? $kept : null;
	}

	/** @return list<int>|null */
	public function beforeRemove(ForumsInterface $subject, int ...$ids): ?array {
		return $this->removing($ids, 'afo_del_forum_qr_delete_forum', 'remove', static fn (int $id): array => array(
			'DELETE'	=> 'forums',
			'WHERE'		=> 'id='.$id
		));
	}

	/** @return list<int>|null */
	public function beforeRemovePermissions(ForumsInterface $subject, int ...$forumIds): ?array {
		return $this->removing($forumIds, 'afo_del_forum_qr_delete_forum_perms', 'removePermissions', static fn (int $id): array => array(
			'DELETE'	=> 'forum_perms',
			'WHERE'		=> 'forum_id='.$id
		));
	}

	/** @return list<int>|null */
	public function beforeRemoveSubscriptions(ForumsInterface $subject, int ...$forumIds): ?array {
		return $this->removing($forumIds, 'afo_del_forum_qr_delete_forum_subscriptions', 'removeSubscriptions', static fn (int $id): array => array(
			'DELETE'	=> 'forum_subscriptions',
			'WHERE'		=> 'forum_id='.$id
		));
	}

	/**
	 * @param list<GroupDefaultsInterface> $result
	 * @return list<GroupDefaultsInterface>
	 */
	public function afterGroupDefaults(ForumsInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'g.g_id, g.g_read_board, g.g_post_replies, g.g_post_topics',
			'FROM'		=> 'groups AS g',
			'WHERE'		=> 'g_id!='.GroupDefaultsInterface::ADMINISTRATORS
		);

		if ($this->queries->changed('afo_save_forum_qr_get_groups', ForumsInterface::class.'::groupDefaults', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$group = new GroupDefaults((int) Markers::markup($row['g_id'] ?? 0), Markers::markup($row['g_read_board'] ?? 0) === '1', Markers::markup($row['g_post_replies'] ?? 0) === '1', Markers::markup($row['g_post_topics'] ?? 0) === '1');
				$this->rows->keep($group, $row);
				$result[] = $group;
			}
		}

		return $result;
	}

	/**
	 * @param list<GroupPermissionsInterface> $result
	 * @return list<GroupPermissionsInterface>
	 */
	public function afterGroupPermissions(ForumsInterface $subject, array $result, int $forumId): array {
		$query = array(
			'SELECT'	=> 'g.g_id, g.g_title, g.g_read_board, g.g_post_replies, g.g_post_topics, fp.read_forum, fp.post_replies, fp.post_topics',
			'FROM'		=> 'groups AS g',
			'JOINS'		=> array(
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> 'g.g_id=fp.group_id AND fp.forum_id='.$forumId
				)
			),
			'WHERE'		=> 'g.g_id!='.GroupDefaultsInterface::ADMINISTRATORS,
			'ORDER BY'	=> 'g.g_id'
		);

		if ($this->queries->changed('afo_qr_get_forum_perms', ForumsInterface::class.'::groupPermissions', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$group = new GroupPermissions(
					(int) Markers::markup($row['g_id'] ?? 0),
					Markers::markup($row['g_title'] ?? ''),
					Markers::markup($row['g_read_board'] ?? 0) === '1',
					Markers::markup($row['g_post_replies'] ?? 0) === '1',
					Markers::markup($row['g_post_topics'] ?? 0) === '1',
					self::stored($row['read_forum'] ?? null),
					self::stored($row['post_replies'] ?? null),
					self::stored($row['post_topics'] ?? null)
				);
				$this->rows->keep($group, $row);
				$result[] = $group;
			}
		}

		return $result;
	}

	/** @return list<int|ForumPermissionsInterface>|null */
	public function beforeUpdatePermissions(ForumsInterface $subject, int $forumId, ForumPermissionsInterface ...$permissions): ?array {
		$kept = array();
		foreach ($permissions as $permission)
		{
			$perms_new_values = array();
			foreach (ForumsRows::values($permission) as $key => $value)
				$perms_new_values[] = $key.'='.$value;

			$GLOBALS['perms_new_values'] = $perms_new_values;

			$query = array(
				'UPDATE'	=> 'forum_perms',
				'WHERE'		=> 'group_id='.$permission->groupId().' AND forum_id='.$forumId,
				'SET'		=> implode(', ', $perms_new_values)
			);

			if ($this->queries->changed('afo_save_forum_qr_update_forum_perms', ForumsInterface::class.'::updatePermissions', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $permission;
		}

		return count($kept) !== count($permissions) ? array($forumId, ...$kept) : null;
	}

	/** @return list<int|ForumPermissionsInterface>|null */
	public function beforeAddPermissions(ForumsInterface $subject, int $forumId, ForumPermissionsInterface ...$permissions): ?array {
		$kept = array();
		foreach ($permissions as $permission)
		{
			$values = ForumsRows::values($permission);

			$query = array(
				'INSERT'	=> 'group_id, forum_id, '.implode(', ', array_keys($values)),
				'INTO'		=> 'forum_perms',
				'VALUES'	=> $permission->groupId().', '.$forumId.', '.implode(', ', $values)
			);

			if ($this->queries->changed('afo_save_forum_qr_add_forum_perms', ForumsInterface::class.'::addPermissions', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $permission;
		}

		return count($kept) !== count($permissions) ? array($forumId, ...$kept) : null;
	}

	/** @return list<int>|null */
	public function beforeRemoveGroupPermissions(ForumsInterface $subject, int $forumId, int ...$groupIds): ?array {
		$kept = $this->removing($groupIds, 'afo_save_forum_qr_delete_group_forum_perms', 'removeGroupPermissions', static fn (int $groupId): array => array(
			'DELETE'	=> 'forum_perms',
			'WHERE'		=> 'group_id='.$groupId.' AND forum_id='.$forumId
		));

		return $kept !== null ? array($forumId, ...$kept) : null;
	}

	/** @return list<int>|null */
	public function beforeRevertPermissions(ForumsInterface $subject, int ...$forumIds): ?array {
		return $this->removing($forumIds, 'afo_revert_perms_qr_revert_forum_perms', 'revertPermissions', static fn (int $id): array => array(
			'DELETE'	=> 'forum_perms',
			'WHERE'		=> 'forum_id='.$id
		));
	}

	/**
	 * @param list<CategoryInterface> $result
	 * @return list<CategoryInterface>
	 */
	private function categories(string $point, string $method, array $result): array {
		$query = array(
			'SELECT'	=> 'c.id, c.cat_name',
			'FROM'		=> 'categories AS c',
			'ORDER BY'	=> 'c.disp_position'
		);

		if ($this->queries->changed($point, ForumsInterface::class.'::'.$method, $query))
			$result = array_map(static fn (array $row): Category => new Category((int) Markers::markup($row['id'] ?? 0), Markers::markup($row['cat_name'] ?? '')), PluggedQuery::rows($query));

		return $result;
	}

	/**
	 * Runs $point over the statement $build makes for each id.
	 *
	 * @param array<int> $ids
	 * @param \Closure(int): array<string, mixed> $build
	 * @return list<int>|null
	 */
	private function removing(array $ids, string $point, string $method, \Closure $build): ?array {
		$kept = array();
		foreach ($ids as $id)
		{
			$query = $build($id);

			if ($this->queries->changed($point, ForumsInterface::class.'::'.$method, $query))
				PluggedQuery::run($query);
			else
				$kept[] = $id;
		}

		return count($kept) !== count($ids) ? $kept : null;
	}

	/** A permission a forum stores, which allows unless it is 0; null when it stores none. */
	private static function stored(mixed $value): ?bool {
		return $value !== null ? Markers::markup($value) !== '0' : null;
	}

	private static function escape(string $text): string {
		return Markers::markup(LegacyConnection::legacy()->escape($text));
	}
}
