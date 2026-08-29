<?php
/**
 * Drives include/dblayer/sqlite3.php end to end in a fresh process.
 *
 * close() is called explicitly by footer.php and again by __destruct(), and
 * shutdown-time fatals are only observable out of process.
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

require FORUM_ROOT.'include/dblayer/sqlite3.php';

$db_name = $argv[1];
$db = new DBLayer('', '', '', $db_name, '', false);

$db->query('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, v TEXT NOT NULL)');
$db->query('INSERT INTO t (v) VALUES (\''.$db->escape('o\'reilly').'\')');
echo 'INSERT_ID='.$db->insert_id()."\n";

$result = $db->query('SELECT v FROM t');
echo 'ROW='.$db->result($result)."\n";

// PHP 8 throws on an already-finalised SQLite3Result.
$db->free_result($result);
$db->free_result($result);

// An empty result set: result() must report failure rather than read a column
// off the bool(false) the fetch returns.
$empty = $db->query('SELECT v FROM t WHERE id = 999');
echo 'EMPTY_RESULT=', var_export($db->result($empty), true), "\n";

// A request that ends inside a transaction: close() has to commit and still
// leave the object safe for the second close() from __destruct().
$db->start_transaction();
$db->query('INSERT INTO t (v) VALUES (\'in-transaction\')');

var_dump($db->close());
var_dump($db->close());

echo "DONE\n";
