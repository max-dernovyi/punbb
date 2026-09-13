<?php
/**
 * Runs the mysqli schema helpers on names that arrive already quoted.
 *
 * 1.4 extensions pass '`field`' to create_table() and add_field(), and 1.4.4
 * concatenated those names raw, so the column came out as `field`. The
 * harness builds such a schema, lists the columns the server holds and
 * queries them by the plain name, the way the extension's own SQL does.
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

// Reports and carries on, so the table is dropped whatever failed.
function error($message, $file = null, $line = null)
{
	global $db;

	echo 'ERROR: '.(isset($db) ? $db->error_msg : $message)."\n";
}

function forum_microtime()
{
	return microtime(true);
}

$db_host = getenv('PUNBB_TEST_MYSQL_HOST');
if ($db_host === false || $db_host === '')
	exit('NO_SERVER');

require FORUM_ROOT.'include/dblayer/'.$argv[1].'.php';

$db = new DBLayer(
	$db_host,
	(string)getenv('PUNBB_TEST_MYSQL_USER'),
	(string)getenv('PUNBB_TEST_MYSQL_PASSWORD'),
	(string)getenv('PUNBB_TEST_MYSQL_DBNAME'),
	'pq_'.getmypid().'_',
	false
);

// The shape of evil_portal's sidebar table.
$db->drop_table('sidebar');
$db->create_table('sidebar', array(
	'FIELDS'		=> array(
		'`id`'				=> array('datatype' => 'SERIAL', 'allow_null' => false),
		'`indeteficate`'	=> array('datatype' => 'VARCHAR(50)', 'allow_null' => false, 'default' => '\'\'')
	),
	'PRIMARY KEY'	=> array('`id`'),
	'INDEXES'		=> array('indeteficate_idx' => array('`indeteficate`(20)'))
));

$db->add_field('sidebar', '`position`', 'INT(10)', false, 0, '`indeteficate`');

$columns = array();
$result = $db->query('SHOW COLUMNS FROM '.$db->prefix.'sidebar');
while ($cur_column = $db->fetch_assoc($result))
	$columns[] = $cur_column['Field'];

echo 'COLUMNS='.implode(',', $columns)."\n";
echo 'ID_EXISTS='.var_export($db->field_exists('sidebar', 'id'), true)."\n";
echo 'POSITION_EXISTS='.var_export($db->field_exists('sidebar', 'position'), true)."\n";
echo 'INDEX_EXISTS='.var_export($db->index_exists('sidebar', 'indeteficate_idx'), true)."\n";

$db->query('INSERT INTO '.$db->prefix.'sidebar (indeteficate, position) VALUES (\'1\', 3), (\'2\', 4)');
$db->query('DELETE FROM '.$db->prefix.'sidebar WHERE indeteficate IN (\'1\')');

$result = $db->query('SELECT id, indeteficate, position FROM '.$db->prefix.'sidebar');
$row = $result ? $db->fetch_assoc($result) : false;
echo 'ROW='.($row ? implode(',', $row) : 'none')."\n";

$db->drop_field('sidebar', 'position');
echo 'POSITION_GONE='.var_export($db->field_exists('sidebar', 'position'), true)."\n";

foreach ($db->get_saved_queries() as $cur_query)
{
	if (strpos($cur_query[0], 'CREATE TABLE') === 0 || strpos($cur_query[0], 'ALTER TABLE') === 0)
		echo 'DDL='.str_replace("\n", ' ', $cur_query[0])."\n";
}

$db->drop_table('sidebar');
echo 'SIDEBAR_GONE='.var_export($db->table_exists('sidebar'), true)."\n";

$db->close();

echo "DONE\n";
