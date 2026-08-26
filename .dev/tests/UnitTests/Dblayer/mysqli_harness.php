<?php
/**
 * Exercises a mysqli driver's result/connection lifecycle in a fresh process.
 *
 * Out of process because every driver declares the same class DBLayer, and
 * because __destruct() fatals are only observable after the script ends.
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
	'lifecycle_'.getmypid().'_',
	false
);

$table = 'lifecycle_'.getmypid().'_t';
$db->query('CREATE TABLE '.$table.' (id INT AUTO_INCREMENT PRIMARY KEY, v VARCHAR(64) NOT NULL)');
$db->query('INSERT INTO '.$table.' (v) VALUES (\''.$db->escape('o\'reilly').'\')');
echo 'INSERT_ID='.$db->insert_id()."\n";

$result = $db->query('SELECT v FROM '.$table);
echo 'ROW='.$db->result($result)."\n";

// Freeing the same result twice: mysqli_free_result() throws on an already
// freed result since PHP 8.0, and @ does not suppress an Error.
$db->free_result($result);
$db->free_result($result);

$db->query('DROP TABLE '.$table);

// The last query was a write, so query_result holds bool(true), not a
// mysqli_result. close() must not hand that to mysqli_free_result().
var_dump($db->close());
var_dump($db->close());

echo "DONE\n";
