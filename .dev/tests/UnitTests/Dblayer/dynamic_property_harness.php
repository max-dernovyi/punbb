<?php
/**
 * Constructs one driver, runs a query cycle, and reports every property that
 * exists on the object but was never declared by the class.
 *
 * Out of process because every driver declares the same class DBLayer.
 * $argv[1] is the driver.
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

$driver = $argv[1];
$table = 'dynprop_'.getmypid();
$sqlite_db = '.dev/build/phpunit/dynprop_'.getmypid().'.db';

switch ($driver)
{
	case 'mysqli':
	case 'mysqli_innodb':
		$host = getenv('PUNBB_TEST_MYSQL_HOST');
		if ($host === false || $host === '')
			exit('NO_SERVER');

		$credentials = array($host, (string)getenv('PUNBB_TEST_MYSQL_USER'), (string)getenv('PUNBB_TEST_MYSQL_PASSWORD'), (string)getenv('PUNBB_TEST_MYSQL_DBNAME'));
		$serial = 'INT AUTO_INCREMENT PRIMARY KEY';
		break;

	case 'pgsql':
		$host = getenv('PUNBB_TEST_PGSQL_HOST');
		if ($host === false || $host === '')
			exit('NO_SERVER');

		$credentials = array($host, (string)getenv('PUNBB_TEST_PGSQL_USER'), (string)getenv('PUNBB_TEST_PGSQL_PASSWORD'), (string)getenv('PUNBB_TEST_PGSQL_DBNAME'));
		$serial = 'SERIAL PRIMARY KEY';
		break;

	case 'sqlite3':
		$credentials = array('', '', '', $sqlite_db);
		$serial = 'INTEGER PRIMARY KEY AUTOINCREMENT';
		break;

	default:
		exit('unknown driver '.$driver);
}

require FORUM_ROOT.'include/dblayer/'.$driver.'.php';

register_shutdown_function(function () use ($sqlite_db) {
	if (file_exists(FORUM_ROOT.$sqlite_db))
		unlink(FORUM_ROOT.$sqlite_db);
});

$db = new DBLayer($credentials[0], $credentials[1], $credentials[2], $credentials[3], $table.'_', false);

$db->query('CREATE TABLE '.$db->quote_identifier($table).' (id '.$serial.', v VARCHAR(64) NOT NULL)');
$db->query('INSERT INTO '.$db->quote_identifier($table).' (v) VALUES (\''.$db->escape('dynamic').'\')');
$result = $db->query('SELECT v FROM '.$db->quote_identifier($table));
$db->result($result);
$db->free_result($result);
$db->query('DROP TABLE '.$db->quote_identifier($table));

// A driver that assigned to an undeclared property leaves it here, whatever
// error_reporting was set to when the deprecation fired.
$declared = array();
foreach ((new ReflectionClass($db))->getProperties() as $property)
	$declared[$property->getName()] = true;

$dynamic = array_diff(array_keys(get_object_vars($db)), array_keys($declared));

echo 'DYNAMIC=', $dynamic === array() ? '(none)' : implode(',', $dynamic), "\n";
echo "DONE\n";
