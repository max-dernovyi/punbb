<?php
/**
 * Reports the charset a mysqli driver escapes by, next to the one it talks in.
 *
 * Out of process because every driver declares the same class DBLayer.
 * The driver to load comes from $argv[1], the server from PUNBB_TEST_MYSQL_*.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');
define('FORUM', 1);
define('FORUM_DATABASE_QUERY_MAXIMUM_LENGTH', 999999);

function error($message, $file = null, $line = null)
{
	echo 'ERROR: '.$message."\n";
	exit(1);
}

$db_host = getenv('PUNBB_TEST_MYSQL_HOST');
if ($db_host === false || $db_host === '')
	exit('NO_SERVER');

$driver = $argv[1];
require FORUM_ROOT.'include/dblayer/'.$driver.'.php';

$db = new DBLayer(
	$db_host,
	(string)getenv('PUNBB_TEST_MYSQL_USER'),
	(string)getenv('PUNBB_TEST_MYSQL_PASSWORD'),
	(string)getenv('PUNBB_TEST_MYSQL_DBNAME'),
	'charset_'.getmypid().'_',
	false
);

// What mysqli_real_escape_string() escapes by, and what the server decodes by.
echo 'CLIENT=', mysqli_character_set_name($db->link_id), "\n";

$result = $db->query('SELECT @@character_set_client');
echo 'SERVER=', $db->result($result), "\n";

$db->close();

echo "DONE\n";
