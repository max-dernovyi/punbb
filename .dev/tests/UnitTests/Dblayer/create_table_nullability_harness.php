<?php
/**
 * Builds a table whose schema leaves allow_null out, on one driver.
 *
 * 1.4 extensions write fields as array('datatype' => ...) alone, and 1.4.4 read
 * the absent key as null, so the column came out NOT NULL. The harness creates
 * that shape and reports each column's nullability as the server holds it.
 *
 * Out of process because every driver declares the same class DBLayer.
 * $argv[1] is the driver; servers come from PUNBB_TEST_MYSQL_* / PUNBB_TEST_PGSQL_*.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');
define('FORUM_DEBUG', 1);

require FORUM_ROOT.'include/autoload.php';
require FORUM_ROOT.'.dev/tests/UnitTests/bootstrap_no_db.php';

$db_type = $argv[1] ?? '';
$db_prefix = 'cn_'.getmypid().'_';
$p_connect = false;

$scratch = '.dev/tmp/phpunit';
if (!is_dir(FORUM_ROOT.$scratch))
	mkdir(FORUM_ROOT.$scratch, 0777, true);

$sqlite_db = $scratch.'/create_table_nullability_'.getmypid().'.db';

switch ($db_type)
{
	case 'mysqli':
	case 'mysqli_innodb':
		$env = 'PUNBB_TEST_MYSQL_';
		break;

	case 'pgsql':
		$env = 'PUNBB_TEST_PGSQL_';
		break;

	case 'sqlite3':
		$env = null;
		break;

	default:
		exit('unknown driver '.$db_type);
}

if ($env === null)
{
	$db_host = $db_username = $db_password = '';
	$db_name = $sqlite_db;
}
else
{
	$db_host = (string) getenv($env.'HOST');
	$db_username = (string) getenv($env.'USER');
	$db_password = (string) getenv($env.'PASSWORD');
	$db_name = (string) getenv($env.'DBNAME');
	if ($db_host === '')
		exit('NO_SERVER');
}

register_shutdown_function(function () use ($sqlite_db) {
	if (file_exists(FORUM_ROOT.$sqlite_db))
		unlink(FORUM_ROOT.$sqlite_db);
});

require FORUM_ROOT.'include/dblayer/common_db.php';

// The shape of evil_wowgallery's screenshots table, beside both explicit values.
$forum_db->create_table('screenshots', array(
	'FIELDS'		=> array(
		'id'		=> array('datatype' => 'SERIAL'),
		'size'		=> array('datatype' => 'INT(10)'),
		'ip'		=> array('datatype' => 'VARCHAR(255)'),
		'nullable'	=> array('datatype' => 'VARCHAR(255)', 'allow_null' => true),
		'required'	=> array('datatype' => 'INT(10)', 'allow_null' => false, 'default' => '0')
	),
	'PRIMARY KEY'	=> array('id')
));

$table = $forum_db->prefix.'screenshots';

if ($db_type == 'pgsql')
	$sql = 'SELECT column_name AS name, is_nullable = \'NO\' AS not_null FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = \''.$forum_db->escape($table).'\' ORDER BY ordinal_position';
else if ($db_type == 'sqlite3')
	$sql = 'SELECT name, "notnull" AS not_null FROM pragma_table_info(\''.$forum_db->escape($table).'\')';
else
	$sql = 'SELECT COLUMN_NAME AS name, IS_NULLABLE = \'NO\' AS not_null FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \''.$forum_db->escape($table).'\' ORDER BY ORDINAL_POSITION';

$columns = array();
$result = $forum_db->query($sql) or error(__FILE__, __LINE__);
while ($row = $forum_db->fetch_assoc($result))
	$columns[] = $row['name'].':'.(in_array($row['not_null'], array(1, '1', 't', true), true) ? 'NOT NULL' : 'NULL');
$forum_db->free_result($result);

echo 'COLUMNS=', implode(',', $columns), "\n";

$forum_db->drop_table('screenshots');
echo 'GONE=', var_export($forum_db->table_exists('screenshots'), true), "\n";

$forum_db->close();

echo "DONE\n";
