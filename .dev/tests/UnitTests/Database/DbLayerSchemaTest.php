<?php
/**
 * A declared table as the array the DBLayer builders take. The builders
 * themselves run out of process, in InstalledSchemaTest: every driver declares
 * the same class DBLayer.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\DbLayerSchema;
use PunBB\Module\Database\Schema\Table;

class DbLayerSchemaTest extends TestCase {
	public function testATableIsTheArrayTheDblayerBuildersTake(): void {
		$table = new Table('t', array(
			new Column('id', 'SERIAL'),
			new Column('name', 'VARCHAR(20)', false, 'it\'s', 'bin'),
			new Column('count', 'INT(10)', false, 0),
			new Column('note', 'TEXT', true),
		), array('id'), array('name_idx' => array('name')), array('count_idx' => array('count')), 'InnoDB');

		$this->assertSame(array(
			'FIELDS'		=> array(
				'id'	=> array('datatype' => 'SERIAL', 'allow_null' => false),
				'name'	=> array('datatype' => 'VARCHAR(20)', 'allow_null' => false, 'default' => '\'it\'\'s\'', 'collation' => 'bin'),
				'count'	=> array('datatype' => 'INT(10)', 'allow_null' => false, 'default' => '0'),
				'note'	=> array('datatype' => 'TEXT', 'allow_null' => true),
			),
			'PRIMARY KEY'	=> array('id'),
			'UNIQUE KEYS'	=> array('name_idx' => array('name')),
			'INDEXES'		=> array('count_idx' => array('count')),
			'ENGINE'		=> 'InnoDB',
		), DbLayerSchema::definition($table));

		$this->assertSame(array('FIELDS' => array('a' => array('datatype' => 'INT', 'allow_null' => false))), DbLayerSchema::definition(new Table('u', array(new Column('a', 'INT')))));
	}
}
