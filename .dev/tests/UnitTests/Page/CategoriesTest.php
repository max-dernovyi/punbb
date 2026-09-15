<?php
/**
 * The categories page's repository over an in-memory SQLite database with the
 * forum's tables: the categories by position and by id, a category's name and
 * forums, and adding, updating and removing them with what they take along.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Categories\Api\Data\CategoryInterface;
use PunBB\Module\Categories\Model\Categories;
use PunBB\Module\Categories\Model\Category;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;

class CategoriesTest extends TestCase {
	private Connection $db;

	private Categories $categories;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');
		$this->db->execute('CREATE TABLE pun_categories (id INTEGER PRIMARY KEY, cat_name VARCHAR(80) NOT NULL DEFAULT \'New Category\', disp_position INTEGER NOT NULL DEFAULT 0)');
		$this->db->execute('CREATE TABLE pun_forums (id INTEGER PRIMARY KEY, forum_name VARCHAR(80) NOT NULL, cat_id INTEGER NOT NULL)');
		$this->db->execute('CREATE TABLE pun_forum_subscriptions (user_id INTEGER NOT NULL, forum_id INTEGER NOT NULL)');
		$this->db->execute('INSERT INTO pun_categories (id, cat_name, disp_position) VALUES (1, ?, 2), (2, ?, 1)', 'Talk', 'News');
		$this->db->execute('INSERT INTO pun_forums (id, forum_name, cat_id) VALUES (1, ?, 1), (2, ?, 2), (3, ?, 1)', 'a', 'b', 'c');
		$this->db->execute('INSERT INTO pun_forum_subscriptions (user_id, forum_id) VALUES (5, 1), (5, 2), (6, 1)');

		$this->categories = new Categories($this->db);
	}

	/**
	 * @param list<CategoryInterface> $categories
	 * @return list<string>
	 */
	private static function listed(array $categories): array {
		return array_map(static fn (CategoryInterface $category): string => $category->id().' '.$category->name().'@'.$category->position(), $categories);
	}

	public function testTheCategoriesComeByPositionOrById(): void {
		$this->assertSame(array('2 News@1', '1 Talk@2'), self::listed($this->categories->all()));
		$this->assertSame(array('1 Talk@2', '2 News@1'), self::listed($this->categories->allById()));
	}

	public function testACategoryIsNamedAndItsForumsFound(): void {
		$this->assertSame('News', $this->categories->name(2));
		$this->assertNull($this->categories->name(9));
		$this->assertSame(array(1, 3), $this->categories->forumIds(1));
		$this->assertSame(array(), $this->categories->forumIds(9));
	}

	public function testCategoriesAreAddedUpdatedAndRemovedWithTheirForums(): void {
		$this->categories->add(new Category(99, 'Games \'n\' fun', 3));
		$this->categories->update(new Category(1, 'Chat', 0));
		$this->categories->removeForums(1, 3);
		$this->categories->removeForumSubscriptions(1);
		$this->categories->remove(2);

		$this->assertSame(array('1 Chat@0', '3 Games \'n\' fun@3'), self::listed($this->categories->all()));
		$this->assertSame(array(), $this->categories->forumIds(1));
		$this->assertSame(array(2), array_map(static fn ($row): int => $row->int('forum_id'), $this->db->select('SELECT forum_id FROM pun_forum_subscriptions')));
	}
}
