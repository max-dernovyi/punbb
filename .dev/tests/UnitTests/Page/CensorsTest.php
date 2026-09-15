<?php
/**
 * The censoring page's repository over an in-memory SQLite database with the
 * forum's table: the words by the word, and adding, updating and removing them.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Censoring\Api\Data\CensorInterface;
use PunBB\Module\Censoring\Model\Censor;
use PunBB\Module\Censoring\Model\Censors;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;

class CensorsTest extends TestCase {
	private Censors $censors;

	protected function setUp(): void {
		$db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');
		$db->execute('CREATE TABLE pun_censoring (id INTEGER PRIMARY KEY, search_for VARCHAR(60) NOT NULL, replace_with VARCHAR(60) NOT NULL)');
		$db->execute('INSERT INTO pun_censoring (id, search_for, replace_with) VALUES (1, ?, ?), (2, ?, ?)', 'zap', 'z*p', 'darn', 'd*rn');

		$this->censors = new Censors($db);
	}

	/** @return list<string> */
	private function listed(): array {
		return array_map(static fn (CensorInterface $censor): string => $censor->id().' '.$censor->searchFor().' '.$censor->replaceWith(), $this->censors->all());
	}

	public function testTheWordsComeByTheWord(): void {
		$this->assertSame(array('2 darn d*rn', '1 zap z*p'), $this->listed());
	}

	public function testWordsAreAddedUpdatedAndRemoved(): void {
		$this->censors->add(new Censor(99, 'heck\'s', 'h*ck'));
		$this->censors->update(new Censor(1, 'zapped', 'z*pped'), new Censor(2, 'darned', 'd*rned'));
		$this->censors->remove(2);

		$this->assertSame(array('3 heck\'s h*ck', '1 zapped z*pped'), $this->listed());

		$this->censors->add();
		$this->censors->remove();
		$this->assertCount(2, $this->censors->all());
	}
}
