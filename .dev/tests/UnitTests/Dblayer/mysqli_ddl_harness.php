<?php
/**
 * Runs the mysqli DDL builders against a live server and echoes the SQL.
 *
 * MySQL 8.0 made GROUPS and RANK reserved words and the forum schema uses
 * both, so every builder has to quote what it emits. The harness executes
 * each statement for real and prints the generated SQL from the driver's own
 * query log, so the test can assert on both.
 *
 * Out of process because every driver declares the same class DBLayer. The
 * server comes from PUNBB_TEST_MYSQL_*, the driver from $argv[1].
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');
define('FORUM', 1);
define('FORUM_DEBUG', 1);
define('FORUM_DATABASE_QUERY_MAXIMUM_LENGTH', 999999);

function error($message, $file = null, $line = null)
{
	echo 'ERROR: '.$message."\n";
	exit(1);
}

// FORUM_DEBUG makes query() log the SQL, which is what the assertions read;
// the timing helper normally comes from include/functions.php.
function forum_microtime()
{
	return microtime(true);
}

$db_host = getenv('PUNBB_TEST_MYSQL_HOST');
if ($db_host === false || $db_host === '')
	exit('NO_SERVER');

require FORUM_ROOT.'include/dblayer/'.$argv[1].'.php';

$prefix = 'ddl_'.getmypid().'_';

$db = new DBLayer(
	$db_host,
	(string)getenv('PUNBB_TEST_MYSQL_USER'),
	(string)getenv('PUNBB_TEST_MYSQL_PASSWORD'),
	(string)getenv('PUNBB_TEST_MYSQL_DBNAME'),
	$prefix,
	false
);

// The two collisions the forum schema actually has, plus a plain table.
$schema = array(
	'FIELDS'	=> array(
		'id'		=> array('datatype' => 'SERIAL', 'allow_null' => false),
		'rank'		=> array('datatype' => 'VARCHAR(50)', 'allow_null' => false, 'default' => '\'\''),
		'ident'		=> array('datatype' => 'VARCHAR(200)', 'allow_null' => false)
	),
	'PRIMARY KEY'	=> array('id'),
	'UNIQUE KEYS'	=> array('rank_idx' => array('rank')),
	'INDEXES'		=> array('ident_idx' => array('ident(40)')),
	'ENGINE'		=> 'InnoDB'
);

$db->drop_table('groups');
$db->create_table('groups', $schema);

$db->drop_table('normal');
$db->create_table('normal', array(
	'FIELDS'		=> array('id' => array('datatype' => 'INT(10)', 'allow_null' => false)),
	'PRIMARY KEY'	=> array('id'),
	'ENGINE'		=> 'InnoDB'
));

echo 'GROUPS_EXISTS='.var_export($db->table_exists('groups'), true)."\n";
echo 'RANK_EXISTS='.var_export($db->field_exists('groups', 'rank'), true)."\n";
echo 'IDENT_INDEX_EXISTS='.var_export($db->index_exists('groups', 'ident_idx'), true)."\n";

$db->add_field('groups', 'min_posts', 'INT(10)', false, 0, 'rank');
$db->alter_field('groups', 'rank', 'VARCHAR(60)', false, '');
$db->add_index('groups', 'min_posts_idx', array('min_posts'));
$db->drop_index('groups', 'min_posts_idx');
$db->drop_field('groups', 'min_posts');

// query_build: a reserved table name is only reachable with an empty prefix,
// but the reference has to come out quoted either way.
echo 'SELECT_SQL='.$db->query_build(array(
	'SELECT'	=> 'g.id',
	'FROM'		=> 'groups AS g'
), true)."\n";

echo 'INSERT_SQL='.$db->query_build(array(
	'INSERT'	=> $db->quote_identifier('rank').', ident',
	'INTO'		=> 'groups',
	'VALUES'	=> '\'New member\', \'x\''
), true)."\n";

echo 'JOIN_SQL='.$db->query_build(array(
	'SELECT'	=> 'g.id',
	'FROM'		=> 'normal AS n',
	'JOINS'		=> array(array('INNER JOIN' => 'groups AS g', 'ON' => 'g.id=n.id'))
), true)."\n";

echo 'UPDATE_SQL='.$db->query_build(array(
	'UPDATE'	=> 'groups',
	'SET'		=> $db->quote_identifier('rank').'=\'Member\'',
	'WHERE'		=> 'id=1'
), true)."\n";

echo 'DELETE_SQL='.$db->query_build(array(
	'DELETE'	=> 'groups',
	'WHERE'		=> 'id=1'
), true)."\n";

echo 'MULTI_FROM='.$db->query_build(array(
	'SELECT'	=> '1',
	'FROM'		=> 'normal AS n, groups AS g'
), true)."\n";

// Not a parser: an expression it cannot recognise comes back verbatim.
echo 'RAW_FROM_UNTOUCHED='.$db->query_build(array(
	'SELECT'	=> '1',
	'FROM'		=> 'normal AS n USE INDEX (ident)'
), true)."\n";

// The same statements, executed.
$db->query_build(array(
	'INSERT'	=> $db->quote_identifier('rank').', ident',
	'INTO'		=> 'groups',
	'VALUES'	=> '\'New member\', \'x\''
)) or error('insert failed');

$result = $db->query_build(array(
	'SELECT'	=> 'g.'.$db->quote_identifier('rank'),
	'FROM'		=> 'groups AS g'
)) or error('select failed');
echo 'ROW='.$db->result($result)."\n";

$db->query_build(array(
	'UPDATE'	=> 'groups',
	'SET'		=> $db->quote_identifier('rank').'=\'Member\'',
	'WHERE'		=> 'id=1'
)) or error('update failed');

$db->query_build(array('DELETE' => 'groups', 'WHERE' => 'id=1')) or error('delete failed');

// The generated DDL, from the driver's own log.
foreach ($db->get_saved_queries() as $cur_query)
{
	if (strpos($cur_query[0], 'CREATE TABLE') === 0 || strpos($cur_query[0], 'ALTER TABLE') === 0)
		echo 'DDL='.str_replace("\n", ' ', $cur_query[0])."\n";
}

$db->drop_table('groups');
$db->drop_table('normal');

echo 'GROUPS_GONE='.var_export($db->table_exists('groups'), true)."\n";

$db->close();

echo "DONE\n";
