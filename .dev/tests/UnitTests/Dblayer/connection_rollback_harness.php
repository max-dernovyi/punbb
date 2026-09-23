<?php
/**
 * Writes a row through the new core's connection inside the forum's
 * transaction, then either sends it a statement the database refuses and lets
 * the forum render the failure ("refused"), or rolls the transaction back as
 * the updater does for a failed patch, writes a second row and commits
 * ("rolled_back"). Once the script is done, a connection of its own counts
 * what was committed: "ROWS=n".
 *
 * Out of process because every driver declares the same class DBLayer and
 * because error() exits. $argv[1] is the driver, $argv[2] the mode; MySQL and
 * PostgreSQL come from PUNBB_TEST_*, and without them the script says NO_SERVER.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Setup\LegacySetupDatabase;

define('FORUM_ROOT', dirname(__DIR__, 4).'/');

require FORUM_ROOT.'include/autoload.php';
require FORUM_ROOT.'.dev/tests/UnitTests/bootstrap_no_db.php';

const SQLITE_FILE = '.dev/tmp/connection_rollback.sqlite3';

// The error page's title
$forum_config['o_board_title'] = 'PunBB';

$db_type = $argv[1] ?? '';
$backend = match ($db_type) {
	'mysqli', 'mysqli_innodb'	=> 'MYSQL',
	'pgsql'						=> 'PGSQL',
	'sqlite3'					=> 'SQLITE',
	default						=> exit('unknown driver '.$db_type),
};

$db_host = $backend !== 'SQLITE' ? (string) getenv('PUNBB_TEST_'.$backend.'_HOST') : '';
if ($backend !== 'SQLITE' && $db_host === '')
	exit('NO_SERVER');

require FORUM_ROOT.'include/dblayer/'.$db_type.'.php';

@unlink(FORUM_ROOT.SQLITE_FILE);

$db_prefix = 'rollback_'.getmypid().'_';
$credentials = array($db_host, (string) getenv('PUNBB_TEST_'.$backend.'_USER'), (string) getenv('PUNBB_TEST_'.$backend.'_PASSWORD'), (string) getenv('PUNBB_TEST_'.$backend.'_DBNAME'));

$forum_db = $backend === 'SQLITE'
	? new DBLayer('', '', '', SQLITE_FILE, $db_prefix, false)
	: new DBLayer($credentials[0], $credentials[1], $credentials[2], $credentials[3], $db_prefix, false);

$forum_db->create_table('rows', array('FIELDS' => array('id' => array('datatype' => 'INT(10) UNSIGNED', 'allow_null' => false))));

register_shutdown_function(static function () use ($backend, $credentials, $db_prefix): void {
	$table = $db_prefix.'rows';

	if ($backend === 'SQLITE')
	{
		$link = new SQLite3(FORUM_ROOT.SQLITE_FILE);
		echo 'ROWS=', $link->querySingle('SELECT COUNT(*) FROM '.$table), "\n";
		$link->close();
		@unlink(FORUM_ROOT.SQLITE_FILE);
	}
	else if ($backend === 'MYSQL')
	{
		$link = new mysqli($credentials[0], $credentials[1], $credentials[2], $credentials[3]);
		echo 'ROWS=', $link->query('SELECT COUNT(*) FROM '.$table)->fetch_row()[0], "\n";
		$link->query('DROP TABLE '.$table);
		$link->close();
	}
	else
	{
		$link = pg_connect('host='.$credentials[0].' dbname='.$credentials[3].' user='.$credentials[1].' password='.$credentials[2]);
		echo 'ROWS=', pg_fetch_row(pg_query($link, 'SELECT COUNT(*) FROM '.$table))[0], "\n";
		pg_query($link, 'DROP TABLE '.$table);
		pg_close($link);
	}

	echo 'DONE';
});

$forum_db->start_transaction();

$db = LegacyConnection::open();
$db->execute('INSERT INTO '.$db->table('rows').' (id) VALUES (?)', 1);
echo "WRITTEN\n";

if (($argv[2] ?? '') === 'rolled_back')
{
	$setup = new LegacySetupDatabase();
	$setup->rollBack();
	$db->execute('INSERT INTO '.$db->table('rows').' (id) VALUES (?)', 2);
	$setup->endTransaction();
	$setup->close();

	exit;
}

$db->execute('INSERT INTO '.$db->table('no_such_table').' (id) VALUES (?)', 2);
echo "NOT REACHED\n";
