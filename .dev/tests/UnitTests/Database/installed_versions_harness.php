<?php
/**
 * Records module versions in the modules table the Database module declares,
 * created through the synchronizer on one driver, and prints what the board
 * reads back before the table exists and after each step: a "<STEP>:<count>" line, then one
 * "<STEP> <module> <schema> <data>" line per module.
 *
 * Out of process because every driver declares the same class DBLayer.
 * $argv[1] is the driver; MySQL and PostgreSQL come from PUNBB_TEST_*, and
 * without them the script says NO_SERVER.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PunBB\Module\Database\Module;
use PunBB\Module\Database\Schema\DbLayerSchema;
use PunBB\Module\Database\Schema\DeclaredSchema;
use PunBB\Module\Database\Schema\SchemaReader;
use PunBB\Module\Database\Schema\SchemaSynchronizer;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\MysqliDriver;
use PunBB\Module\Database\Sql\Driver\PgsqlDriver;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Database\Version\InstalledVersions;

define('FORUM_ROOT', dirname(__DIR__, 4).'/');

require FORUM_ROOT.'include/autoload.php';
require FORUM_ROOT.'.dev/tests/UnitTests/bootstrap_no_db.php';

const SQLITE_FILE = '.dev/tmp/installed_versions.sqlite3';

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

$forum_db = $backend === 'SQLITE'
	? new DBLayer('', '', '', SQLITE_FILE, 'versions_', false)
	: new DBLayer($db_host, (string) getenv('PUNBB_TEST_'.$backend.'_USER'), (string) getenv('PUNBB_TEST_'.$backend.'_PASSWORD'), (string) getenv('PUNBB_TEST_'.$backend.'_DBNAME'), 'versions_'.getmypid().'_', false);

$connection = new Connection(match ($backend) {
	'MYSQL'		=> new MysqliDriver($forum_db->link_id),
	'PGSQL'		=> new PgsqlDriver($forum_db->link_id),
	'SQLITE'	=> new Sqlite3Driver($forum_db->link_id),
}, $forum_db->prefix);

$platform = Platform::ofDbType($db_type);
$declared = new DeclaredSchema(new Module());
$versions = new InstalledVersions($connection, new SchemaReader($connection));

function report(string $step, InstalledVersions $versions): void
{
	$all = $versions->all();
	ksort($all);

	echo $step, ':', count($all), "\n";
	foreach ($all as $module => $version)
		echo $step, ' ', $module, ' ', $version->schema, ' ', $version->data, "\n";
}

try
{
	// A board from before module versions: no table, no module recorded
	report('NONE', $versions);

	(new SchemaSynchronizer($declared, new DbLayerSchema($forum_db, $connection)))->synchronize($platform);
	report('EMPTY', $versions);

	$versions->recordSchema('Polls', '1.1.0');
	report('SCHEMA', $versions);

	$versions->recordData('Polls', '1.0.0');
	$versions->recordData('Forums', '1.4.0');
	report('DATA', $versions);

	$versions->recordSchema('Polls', '1.10.0');
	report('AGAIN', $versions);
}
finally
{
	foreach ($declared->tables($platform) as $table)
		$forum_db->drop_table($table->name);

	$forum_db->close();
	@unlink(FORUM_ROOT.SQLITE_FILE);
}

echo 'DONE';
