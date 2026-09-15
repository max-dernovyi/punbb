<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Categories;

use PunBB\Module\Categories\Api\CategoriesInterface;
use PunBB\Module\Categories\Api\Data\CategoryInterface;
use PunBB\Module\Categories\Model\Category;
use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;

/**
 * The categories page's query points, with the query arrays
 * admin/categories.php built. A query a point changed answers instead; a
 * statement a point changed runs instead, and the repository is handed nothing
 * to store. The categories listed are left in $cat_list, a deleted category's
 * name in $cat_name and its forums in $forum_ids, each forum in $cur_forum.
 */
final class CategoriesPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	/**
	 * @param list<CategoryInterface> $result
	 * @return list<CategoryInterface>
	 */
	public function afterAll(CategoriesInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'c.id, c.cat_name, c.disp_position',
			'FROM'		=> 'categories AS c',
			'ORDER BY'	=> 'c.disp_position'
		);

		if ($this->queries->changed('acg_qr_get_categories', CategoriesInterface::class.'::all', $query))
			$result = array_map(self::category(...), PluggedQuery::rows($query));

		$GLOBALS['cat_list'] = array_map(static fn (CategoryInterface $category): array => array(
			'id'			=> $category->id(),
			'cat_name'		=> $category->name(),
			'disp_position'	=> $category->position(),
		), $result);

		return $result;
	}

	/**
	 * @param list<CategoryInterface> $result
	 * @return list<CategoryInterface>
	 */
	public function afterAllById(CategoriesInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'c.id, c.cat_name, c.disp_position',
			'FROM'		=> 'categories AS c',
			'ORDER BY'	=> 'c.id'
		);

		if ($this->queries->changed('acg_update_cats_qr_get_categories', CategoriesInterface::class.'::allById', $query))
			$result = array_map(self::category(...), PluggedQuery::rows($query));

		return $result;
	}

	public function afterName(CategoriesInterface $subject, ?string $result, int $id): ?string {
		$query = array(
			'SELECT'	=> 'c.cat_name',
			'FROM'		=> 'categories AS c',
			'WHERE'		=> 'c.id='.$id
		);

		if ($this->queries->changed('acg_del_cat_qr_get_category_name', CategoriesInterface::class.'::name', $query))
		{
			$name = PluggedQuery::value($query);
			$result = $name !== null && $name !== false ? Markers::markup($name) : null;
		}

		$GLOBALS['cat_name'] = $result;

		return $result;
	}

	/**
	 * @param list<int> $result
	 * @return list<int>
	 */
	public function afterForumIds(CategoriesInterface $subject, array $result, int $id): array {
		$query = array(
			'SELECT'	=> 'f.id',
			'FROM'		=> 'forums AS f',
			'WHERE'		=> 'cat_id='.$id
		);

		if ($this->queries->changed('acg_del_cat_qr_get_forums_to_delete', CategoriesInterface::class.'::forumIds', $query))
			$result = array_map(static fn (array $row): int => (int) Markers::markup($row['id'] ?? 0), PluggedQuery::rows($query));

		$GLOBALS['forum_ids'] = $result;

		return $result;
	}

	/** @return list<CategoryInterface>|null */
	public function beforeAdd(CategoriesInterface $subject, CategoryInterface ...$categories): ?array {
		$kept = array();
		foreach ($categories as $category)
		{
			$query = array(
				'INSERT'	=> 'cat_name, disp_position',
				'INTO'		=> 'categories',
				'VALUES'	=> '\''.self::escape($category->name()).'\', '.$category->position()
			);

			if ($this->queries->changed('acg_add_cat_qr_add_category', CategoriesInterface::class.'::add', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $category;
		}

		return count($kept) !== count($categories) ? $kept : null;
	}

	/** @return list<CategoryInterface>|null */
	public function beforeUpdate(CategoriesInterface $subject, CategoryInterface ...$categories): ?array {
		$kept = array();
		foreach ($categories as $category)
		{
			$GLOBALS['cur_cat'] = array('id' => $category->id(), 'cat_name' => $category->name(), 'disp_position' => $category->position());

			$query = array(
				'UPDATE'	=> 'categories',
				'SET'		=> 'cat_name=\''.self::escape($category->name()).'\', disp_position='.$category->position(),
				'WHERE'		=> 'id='.$category->id()
			);

			if ($this->queries->changed('acg_update_cats_qr_update_category', CategoriesInterface::class.'::update', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $category;
		}

		return count($kept) !== count($categories) ? $kept : null;
	}

	/** @return list<int>|null */
	public function beforeRemoveForums(CategoriesInterface $subject, int ...$forumIds): ?array {
		return $this->removing($forumIds, 'acg_del_cat_qr_delete_forum', 'removeForums', static fn (int $forumId): array => array(
			'DELETE'	=> 'forums',
			'WHERE'		=> 'id='.$forumId
		));
	}

	/** @return list<int>|null */
	public function beforeRemoveForumSubscriptions(CategoriesInterface $subject, int ...$forumIds): ?array {
		return $this->removing($forumIds, 'acg_del_cat_qr_delete_forum_subscriptions', 'removeForumSubscriptions', static fn (int $forumId): array => array(
			'DELETE'	=> 'forum_subscriptions',
			'WHERE'		=> 'forum_id='.$forumId
		));
	}

	/** @return list<int>|null */
	public function beforeRemove(CategoriesInterface $subject, int ...$ids): ?array {
		$kept = array();
		foreach ($ids as $id)
		{
			$query = array(
				'DELETE'	=> 'categories',
				'WHERE'		=> 'id='.$id
			);

			if ($this->queries->changed('acg_del_cat_qr_delete_category', CategoriesInterface::class.'::remove', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $id;
		}

		return count($kept) !== count($ids) ? $kept : null;
	}

	/**
	 * Runs $point over the statement $build makes for each forum, with the forum in $cur_forum.
	 *
	 * @param array<int> $forumIds
	 * @param \Closure(int): array<string, mixed> $build
	 * @return list<int>|null
	 */
	private function removing(array $forumIds, string $point, string $method, \Closure $build): ?array {
		$kept = array();
		foreach ($forumIds as $forumId)
		{
			$GLOBALS['cur_forum'] = $forumId;
			$query = $build($forumId);

			if ($this->queries->changed($point, CategoriesInterface::class.'::'.$method, $query))
				PluggedQuery::run($query);
			else
				$kept[] = $forumId;
		}

		return count($kept) !== count($forumIds) ? $kept : null;
	}

	/** @param array<array-key, mixed> $row */
	private static function category(array $row): Category {
		return new Category((int) Markers::markup($row['id'] ?? 0), Markers::markup($row['cat_name'] ?? ''), (int) Markers::markup($row['disp_position'] ?? 0));
	}

	private static function escape(string $text): string {
		return Markers::markup(LegacyConnection::legacy()->escape($text));
	}
}
