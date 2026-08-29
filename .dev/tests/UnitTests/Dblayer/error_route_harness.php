<?php
/**
 * Drives one driver into one failure mode and lets the forum render it.
 *
 * Since PHP 8.1 mysqli throws where it used to return false, so the drivers
 * catch and route every failure into error(). This harness runs that route for
 * real — the real error() from include/functions.php, not a stub — so the test
 * can assert on the page a visitor would get instead of a stack trace.
 *
 * Out of process because every driver declares the same class DBLayer and
 * because error() exits. $argv[1] is the driver, $argv[2] the mode.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');
define('FORUM', 1);

// Without it error() renders the generic page and never reports what the
// database said or which call site asked for it.
define('FORUM_DEBUG', 1);

require FORUM_ROOT.'include/constants.php';
require FORUM_ROOT.'include/functions.php';

$driver = $argv[1];
$mode = $argv[2];

// PHPUnit's cache directory doubles as scratch space, but the harness runs
// out of process and cannot assume PHPUnit created it first.
$scratch = '.dev/tmp/phpunit';
if (!is_dir(FORUM_ROOT.$scratch))
	mkdir(FORUM_ROOT.$scratch, 0777, true);

$sqlite_db = $scratch.'/error_route_'.getmypid().'.db';

switch ($driver)
{
	case 'mysqli':
	case 'mysqli_innodb':
		$host = getenv('PUNBB_TEST_MYSQL_HOST');
		if ($host === false || $host === '')
			exit('NO_SERVER');

		$credentials = array(
			$host,
			(string)getenv('PUNBB_TEST_MYSQL_USER'),
			($mode == 'bad_connect') ? 'not-the-password' : (string)getenv('PUNBB_TEST_MYSQL_PASSWORD'),
			(string)getenv('PUNBB_TEST_MYSQL_DBNAME')
		);
		break;

	case 'pgsql':
		$host = getenv('PUNBB_TEST_PGSQL_HOST');
		if ($host === false || $host === '')
			exit('NO_SERVER');

		$credentials = array(
			$host,
			(string)getenv('PUNBB_TEST_PGSQL_USER'),
			($mode == 'bad_connect') ? 'not-the-password' : (string)getenv('PUNBB_TEST_PGSQL_PASSWORD'),
			(string)getenv('PUNBB_TEST_PGSQL_DBNAME')
		);
		break;

	case 'sqlite3':
		// A directory passes the readable/writable checks and then fails in the
		// SQLite3 constructor, which throws.
		$credentials = array('', '', '', ($mode == 'bad_connect') ? $scratch : $sqlite_db);
		break;

	default:
		exit('unknown driver '.$driver);
}

require FORUM_ROOT.'include/dblayer/'.$driver.'.php';

register_shutdown_function(function () use ($sqlite_db) {
	if (file_exists(FORUM_ROOT.$sqlite_db))
		unlink(FORUM_ROOT.$sqlite_db);
});

$forum_db = new DBLayer($credentials[0], $credentials[1], $credentials[2], $credentials[3], '', false);

echo "CONNECTED\n";

if ($mode == 'bad_sql')
	$forum_db->query('SELECT * FROM a_table_that_does_not_exist');
else if ($mode == 'bad_ddl')
{
	// What admin/install.php does: a schema error inside a DDL builder used to
	// escape as an uncaught mysqli_sql_exception with a stack trace.
	// A primary key over a column the table does not have: rejected by every
	// server, where an unknown datatype is not (SQLite has none).
	$forum_db->create_table('error_route', array(
		'FIELDS'		=> array('id' => array('datatype' => 'INTEGER', 'allow_null' => false)),
		'PRIMARY KEY'	=> array('no_such_column')
	));
}
else
{
	$result = $forum_db->query('SELECT 1');
	echo 'SELECT=', $forum_db->result($result), "\n";
}

$forum_db->close();

echo "DONE\n";
