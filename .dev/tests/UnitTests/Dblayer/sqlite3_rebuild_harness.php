<?php
/**
 * Rebuilds a SQLite table with two unique keys through add_field(),
 * alter_field() and drop_field(), printing the table's SQL after each and
 * whether a duplicate still breaks each key.
 *
 * Out of process because the driver declares class DBLayer, the name every
 * other driver also uses.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');

require FORUM_ROOT.'include/autoload.php';
require FORUM_ROOT.'.dev/tests/UnitTests/bootstrap_no_db.php';
require FORUM_ROOT.'include/dblayer/sqlite3.php';

$scratch = '.dev/tmp/phpunit';
if (!is_dir(FORUM_ROOT.$scratch))
	mkdir(FORUM_ROOT.$scratch, 0777, true);

$db_name = $scratch.'/sqlite3_rebuild_'.getmypid().'.db';
register_shutdown_function(function () use ($db_name) {
	if (file_exists(FORUM_ROOT.$db_name))
		unlink(FORUM_ROOT.$db_name);
});

$db = new DBLayer('', '', '', $db_name, '', false);

$db->create_table('pairs', array(
	'FIELDS'		=> array(
		'id'	=> array('datatype' => 'INT(10)', 'allow_null' => false),
		'a'		=> array('datatype' => 'VARCHAR(20)', 'allow_null' => false, 'default' => '\'\''),
		'b'		=> array('datatype' => 'VARCHAR(20)', 'allow_null' => false, 'default' => '\'\''),
		'c'		=> array('datatype' => 'VARCHAR(20)', 'allow_null' => true)
	),
	'PRIMARY KEY'	=> array('id'),
	'UNIQUE KEYS'	=> array(
		'a_idx'	=> array('a'),
		'b_idx'	=> array('b')
	)
));
$db->query('INSERT INTO pairs (id, a, b) VALUES (1, \'x\', \'y\')');

/** Whether a row repeating one key's value is refused. */
function refused(DBLayer $db, string $sql): string
{
	try {
		$db->link_id->exec($sql);
	}
	catch (Exception) {
		return 'refused';
	}

	return 'accepted';
}

$report = function (string $step) use ($db): void {
	$sql = $db->link_id->querySingle('SELECT sql FROM sqlite_master WHERE type = \'table\' AND name = \'pairs\'');
	echo $step, ' UNIQUE=', preg_match_all('/^UNIQUE \((a|b)\)/m', (string) $sql), "\n";
	echo $step, ' DUPLICATE_A=', refused($db, 'INSERT INTO pairs (id, a, b) VALUES (2, \'x\', \'other\')'), "\n";
	echo $step, ' DUPLICATE_B=', refused($db, 'INSERT INTO pairs (id, a, b) VALUES (3, \'other\', \'y\')'), "\n";
};

$db->add_field('pairs', 'd', 'INT(10)', true);
$report('ADD');
$db->alter_field('pairs', 'c', 'VARCHAR(40)', true);
$report('ALTER');
$db->drop_field('pairs', 'd');
$report('DROP');

$db->add_field('pairs', 'lang', 'VARCHAR(25)', false, 'English');
$db->add_field('pairs', 'note', 'VARCHAR(25)', true);
$row = $db->link_id->querySingle('SELECT lang, note FROM pairs WHERE id = 1', true);
echo 'TEXT_DEFAULT=', var_export($row['lang'] ?? false, true), "\n";
echo 'NULL_DEFAULT=', var_export(array_key_exists('note', $row) ? $row['note'] : false, true), "\n";

$db->close();

echo "DONE\n";
