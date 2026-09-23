<?php
/**
 * Synchronizes the schema the modules declare into an empty database on one
 * driver, then opens a gap and closes it again, printing what the differ
 * asked for at each point: "FRESH", "GAP" and "CLOSED" lines, one change each.
 *
 * The gap: a table dropped, a column dropped, an index dropped, an index
 * rebuilt over another column, the password column narrowed to the 40
 * characters 1.4 stored a SHA-1 in — no change where SQLite ignores the length —,
 * an indexed column and a primary key column altered, and a column and an index
 * 1.2 had put back. "KEPT" lines name the index and the key the alters must keep;
 * on SQLite, "REBUILT" lines follow a table with both keys rebuilt twice.
 *
 * Out of process because every driver declares the same class DBLayer.
 * $argv[1] is the driver; MySQL and PostgreSQL come from PUNBB_TEST_*, and
 * without them the script says NO_SERVER.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PunBB\Module\Database\Schema\DbLayerSchema;
use PunBB\Module\Database\Schema\DeclaredSchema;
use PunBB\Module\Database\Schema\SchemaSynchronizer;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\MysqliDriver;
use PunBB\Module\Database\Sql\Driver\PgsqlDriver;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Framework\Modules\ModuleRegistry;

define('FORUM_ROOT', dirname(__DIR__, 4).'/');

require FORUM_ROOT.'include/autoload.php';
require FORUM_ROOT.'.dev/tests/UnitTests/bootstrap_no_db.php';

const SQLITE_FILE = '.dev/tmp/installed_schema.sqlite3';

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
	? new DBLayer('', '', '', SQLITE_FILE, 'schema_', false)
	: new DBLayer($db_host, (string) getenv('PUNBB_TEST_'.$backend.'_USER'), (string) getenv('PUNBB_TEST_'.$backend.'_PASSWORD'), (string) getenv('PUNBB_TEST_'.$backend.'_DBNAME'), 'schema_'.getmypid().'_', false);

$connection = new Connection(match ($backend) {
	'MYSQL'		=> new MysqliDriver($forum_db->link_id),
	'PGSQL'		=> new PgsqlDriver($forum_db->link_id),
	'SQLITE'	=> new Sqlite3Driver($forum_db->link_id),
}, $forum_db->prefix);

$platform = Platform::ofDbType($db_type);
$declared = new DeclaredSchema(...ModuleRegistry::discover(FORUM_ROOT.'include/PunBB/Module', 'PunBB\\Module\\')->modules());
$schema = new DbLayerSchema($forum_db, $connection);
$synchronizer = new SchemaSynchronizer($declared, $schema);

/** @param list<PunBB\Module\Database\Schema\Change\ChangeInterface> $changes */
function report(string $point, array $changes): void
{
	echo $point, ':', count($changes), "\n";
	foreach ($changes as $change)
		echo $point, ' ', $change->describe(), "\n";
}

try
{
	echo 'CREATED:', count($synchronizer->synchronize($platform)), "\n";
	report('FRESH', $synchronizer->changes($platform));

	$forum_db->drop_table('forum_subscriptions');
	$forum_db->drop_field('users', 'auto_notify');
	$forum_db->drop_index('topics', 'last_post_idx');
	$forum_db->drop_index('online', 'ident_idx');
	$forum_db->add_index('online', 'ident_idx', array('logged'));
	$forum_db->alter_field('users', 'password', 'VARCHAR(40)', false, '');
	$forum_db->add_field('users', 'save_pass', 'TINYINT(1)', false, 1);
	$forum_db->add_index('online', 'user_id_idx', array('user_id'));
	$forum_db->alter_field('users', 'registered', 'INT(10) UNSIGNED', true);
	$forum_db->alter_field('forum_perms', 'forum_id', 'INT(10)', false, 5);

	report('GAP', $synchronizer->synchronize($platform));
	report('CLOSED', $synchronizer->changes($platform));

	echo 'KEPT index users.registered_idx: ', implode(',', $schema->describe('users')?->index('registered_idx')?->columns ?? array()), "\n";
	echo 'KEPT primary key forum_perms: ', implode(',', $schema->describe('forum_perms')?->primaryKey ?? array()), "\n";

	// SQLite rebuilds a table to alter it: search_words has a primary key and a unique key there, and survives two rebuilds
	if ($backend === 'SQLITE')
	{
		$forum_db->alter_field('search_words', 'word', 'VARCHAR(20)', false, '');
		$forum_db->alter_field('search_words', 'word', 'VARCHAR(20)', false, '');
		report('REBUILT', $synchronizer->changes($platform));
	}
}
finally
{
	foreach ($declared->tables($platform) as $table)
		$forum_db->drop_table($table->name);

	$forum_db->close();
	@unlink(FORUM_ROOT.SQLITE_FILE);
}

echo 'DONE';
