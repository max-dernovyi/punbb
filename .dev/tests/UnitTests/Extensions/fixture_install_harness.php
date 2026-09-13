<?php
/**
 * Runs punbb_fixture's <install> and <uninstall> code against one driver.
 *
 * The schema an extension builds is where the four drivers diverge, so it runs
 * for real: scratch copies of the core tables the fixture touches, created from
 * the installer's own schema, then install, a marker, a reinstall, uninstall.
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
require FORUM_ROOT.'include/common_admin.php';
require FORUM_ROOT.'include/xml.php';

const FIXTURE_DIR = FORUM_ROOT.'.dev/tests/fixtures/extensions/punbb_fixture';


/** A core table's schema array, read out of admin/install.php so it cannot drift. */
function fixture_installer_schema($table)
{
	$source = (string) file_get_contents(FORUM_ROOT.'admin/install.php');
	$end = strpos($source, '$forum_db->create_table(\''.$table.'\', $schema);');
	$start = strrpos(substr($source, 0, $end), '$schema = array(') + strlen('$schema = ');

	return eval('return '.rtrim(trim(substr($source, $start, $end - $start)), ';').';');
}


/** What the server says a table's columns are, in its own terms. */
function fixture_columns($db_type, $table)
{
	global $forum_db;

	$name = $forum_db->prefix.$table;

	if ($db_type == 'pgsql')
		$sql = 'SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = \''.$forum_db->escape($name).'\' ORDER BY ordinal_position';
	else if ($db_type == 'sqlite3')
		$sql = 'PRAGMA table_info('.$name.')';
	else
		$sql = 'SHOW COLUMNS FROM '.$forum_db->quote_identifier($name);

	$columns = array();
	$result = $forum_db->query($sql) or error(__FILE__, __LINE__);
	while ($row = $forum_db->fetch_assoc($result))
		$columns[] = $row;

	return $columns;
}


/** The config table as the config cache would hand it to the next request. */
function fixture_config()
{
	global $forum_db;

	$config = array();
	$result = $forum_db->query_build(array('SELECT' => 'c.conf_name, c.conf_value', 'FROM' => 'config AS c')) or error(__FILE__, __LINE__);
	while ($row = $forum_db->fetch_assoc($result))
		$config[$row['conf_name']] = $row['conf_value'];

	return $config;
}


// The result is freed: SQLite refuses to drop a table a live statement still reads.
function fixture_scalar($query)
{
	global $forum_db;

	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$value = $forum_db->result($result);
	$forum_db->free_result($result);

	return $value;
}


function fixture_report($name, $value)
{
	echo $name, '=', is_string($value) ? $value : var_export($value, true), "\n";
}


$db_type = $argv[1] ?? '';
$p_connect = false;

// PHPUnit's cache directory doubles as scratch space, but the harness runs
// out of process and cannot assume PHPUnit created it first.
$scratch = '.dev/tmp/phpunit';
if (!is_dir(FORUM_ROOT.$scratch))
	mkdir(FORUM_ROOT.$scratch, 0777, true);

$sqlite_db = $scratch.'/fixture_install_'.getmypid().'.db';

switch ($db_type)
{
	case 'mysqli':
	case 'mysqli_innodb':
		$db_host = (string) getenv('PUNBB_TEST_MYSQL_HOST');
		$db_username = (string) getenv('PUNBB_TEST_MYSQL_USER');
		$db_password = (string) getenv('PUNBB_TEST_MYSQL_PASSWORD');
		$db_name = (string) getenv('PUNBB_TEST_MYSQL_DBNAME');
		if ($db_host === '')
			exit('NO_SERVER');

		// A prefix per driver: both MySQL drivers share one database.
		$db_prefix = $db_type == 'mysqli' ? 'fx1_' : 'fx2_';
		break;

	case 'pgsql':
		$db_host = (string) getenv('PUNBB_TEST_PGSQL_HOST');
		$db_username = (string) getenv('PUNBB_TEST_PGSQL_USER');
		$db_password = (string) getenv('PUNBB_TEST_PGSQL_PASSWORD');
		$db_name = (string) getenv('PUNBB_TEST_PGSQL_DBNAME');
		if ($db_host === '')
			exit('NO_SERVER');

		$db_prefix = 'fx3_';
		break;

	case 'sqlite3':
		$db_host = $db_username = $db_password = $db_prefix = '';
		$db_name = $sqlite_db;
		break;

	default:
		exit('unknown driver '.$db_type);
}

register_shutdown_function(function () use ($sqlite_db) {
	if (file_exists(FORUM_ROOT.$sqlite_db))
		unlink(FORUM_ROOT.$sqlite_db);
});

require FORUM_ROOT.'include/dblayer/common_db.php';

// A failed run leaves its tables behind; the next one starts from nothing.
foreach (array('punbb_fixture_markers', 'forums', 'config') as $table)
	$forum_db->drop_table($table);

$forum_db->create_table('config', fixture_installer_schema('config'));
$forum_db->create_table('forums', fixture_installer_schema('forums'));
$forum_db->query_build(array('INSERT' => 'forum_name, cat_id', 'INTO' => 'forums', 'VALUES' => '\'Scratch forum\', 1')) or error(__FILE__, __LINE__);

$forum_config = fixture_config();
$columns_before = fixture_columns($db_type, 'forums');

$ext_data = xml_to_array((string) file_get_contents(FIXTURE_DIR.'/manifest.xml'));

// Global scope, as admin/extensions.php evaluates it.
eval($ext_data['extension']['install']);
$forum_config = fixture_config();

fixture_report('MARKERS_TABLE', $forum_db->table_exists('punbb_fixture_markers'));
fixture_report('HIDDEN_FIELD', $forum_db->field_exists('forums', 'punbb_fixture_hidden'));
fixture_report('CONFIG', $forum_config['o_punbb_fixture_banner'] ?? '(none)');

require FIXTURE_DIR.'/functions.php';

$_SERVER['HTTP_X_PUNBB_FIXTURE_REQUEST'] = 'harness-1';
punbb_fixture_mark('punbb_fixture', 'harness', array('subject' => "Ümlaut 'quoted' \\ done"));

$result = $forum_db->query_build(array('SELECT' => 'm.request_id, m.extension_id, m.hook_id, m.seen', 'FROM' => 'punbb_fixture_markers AS m')) or error(__FILE__, __LINE__);
fixture_report('MARKER', json_encode($forum_db->fetch_assoc($result)));
$forum_db->free_result($result);

// The narrowing in_qr_get_cats_and_forums applies, over the column install added.
$visible = array('SELECT' => 'COUNT(f.id)', 'FROM' => 'forums AS f', 'WHERE' => '(f.cat_id=1) AND f.punbb_fixture_hidden=0');
fixture_report('VISIBLE', fixture_scalar($visible));
$forum_db->query_build(array('UPDATE' => 'forums', 'SET' => 'punbb_fixture_hidden=1')) or error(__FILE__, __LINE__);
fixture_report('VISIBLE_HIDDEN', fixture_scalar($visible));

// An install over an installed version: admin/extensions.php defines EXT_CUR_VERSION and runs <install> again.
define('EXT_CUR_VERSION', '1.0.0');
eval($ext_data['extension']['install']);
$forum_config = fixture_config();

fixture_report('REINSTALL_MARKERS', fixture_scalar(array('SELECT' => 'COUNT(m.id)', 'FROM' => 'punbb_fixture_markers AS m')));
fixture_report('REINSTALL_HIDDEN', fixture_scalar(array('SELECT' => 'COUNT(f.id)', 'FROM' => 'forums AS f', 'WHERE' => 'f.punbb_fixture_hidden=1')));

eval($ext_data['extension']['uninstall']);
$forum_config = fixture_config();
$columns_after = fixture_columns($db_type, 'forums');

fixture_report('UNINSTALL_MARKERS_TABLE', $forum_db->table_exists('punbb_fixture_markers'));
fixture_report('UNINSTALL_HIDDEN_FIELD', $forum_db->field_exists('forums', 'punbb_fixture_hidden'));
fixture_report('UNINSTALL_CONFIG', $forum_config['o_punbb_fixture_banner'] ?? '(none)');
fixture_report('UNINSTALL_FORUMS', fixture_scalar(array('SELECT' => 'COUNT(f.id)', 'FROM' => 'forums AS f')));
fixture_report('COLUMNS_BEFORE', json_encode($columns_before));
fixture_report('COLUMNS_AFTER', json_encode($columns_after));

foreach (array('forums', 'config') as $table)
	$forum_db->drop_table($table);

$forum_db->close();

echo "DONE\n";
