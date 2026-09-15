<?php

declare(strict_types=1);

namespace PunBB\Module\Categories\Model;

use PunBB\Module\Categories\Api\CategoriesInterface;
use PunBB\Module\Categories\Api\Data\CategoryInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;

/**
 * The categories, read from and written to the categories table, with the
 * forums and forum subscriptions a category takes with it.
 */
final class Categories implements CategoriesInterface {
	public function __construct(private readonly Connection $db) {}

	public function all(): array {
		return $this->listed('c.disp_position');
	}

	public function allById(): array {
		return $this->listed('c.id');
	}

	public function name(int $id): ?string {
		$name = $this->db->selectValue('SELECT c.cat_name FROM '.$this->db->table('categories').' AS c WHERE c.id=?', $id);

		return $name !== null ? (string) $name : null;
	}

	public function forumIds(int $id): array {
		return array_map(static fn (Row $row): int => $row->int('id'), $this->db->select('SELECT f.id FROM '.$this->db->table('forums').' AS f WHERE f.cat_id=?', $id));
	}

	public function add(CategoryInterface ...$categories): void {
		foreach ($categories as $category)
			$this->db->execute('INSERT INTO '.$this->db->table('categories').' (cat_name, disp_position) VALUES (?, ?)', $category->name(), $category->position());
	}

	public function update(CategoryInterface ...$categories): void {
		foreach ($categories as $category)
			$this->db->execute('UPDATE '.$this->db->table('categories').' SET cat_name=?, disp_position=? WHERE id=?', $category->name(), $category->position(), $category->id());
	}

	public function removeForums(int ...$forumIds): void {
		foreach ($forumIds as $forumId)
			$this->db->execute('DELETE FROM '.$this->db->table('forums').' WHERE id=?', $forumId);
	}

	public function removeForumSubscriptions(int ...$forumIds): void {
		foreach ($forumIds as $forumId)
			$this->db->execute('DELETE FROM '.$this->db->table('forum_subscriptions').' WHERE forum_id=?', $forumId);
	}

	public function remove(int ...$ids): void {
		foreach ($ids as $id)
			$this->db->execute('DELETE FROM '.$this->db->table('categories').' WHERE id=?', $id);
	}

	/** @return list<Category> */
	private function listed(string $order): array {
		return array_map(static fn (Row $row): Category => new Category($row->int('id'), $row->string('cat_name'), $row->int('disp_position')),
			$this->db->select('SELECT c.id, c.cat_name, c.disp_position FROM '.$this->db->table('categories').' AS c ORDER BY '.$order));
	}
}
