<?php
/**
 * Exercises the pgsql driver's result/connection lifecycle in a fresh process.
 *
 * Out of process because the driver declares class DBLayer, the same name every
 * other driver uses, and because its failure branches call error(), which exits.
 * Connection details come from PUNBB_TEST_PGSQL_*; without them the script says
 * so and the test skips.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');
define('FORUM', 1);

require FORUM_ROOT.'include/constants.php';
require FORUM_ROOT.'include/functions.php';

$db_host = getenv('PUNBB_TEST_PGSQL_HOST');
if ($db_host === false || $db_host === '')
	exit('NO_SERVER');

require FORUM_ROOT.'include/dblayer/pgsql.php';

$db = new DBLayer(
	$db_host,
	(string)getenv('PUNBB_TEST_PGSQL_USER'),
	(string)getenv('PUNBB_TEST_PGSQL_PASSWORD'),
	(string)getenv('PUNBB_TEST_PGSQL_DBNAME'),
	'',
	false
);

$table = 'punbb_pgsql_harness';
$db->query('DROP TABLE IF EXISTS '.$table);
$db->query('CREATE TABLE '.$table.' (id SERIAL PRIMARY KEY, label VARCHAR(20) NOT NULL)');

// A plain read: the result must be usable and must survive being recorded.
$result = $db->query('SELECT 1');
echo 'SELECT=', ($result ? 'ok' : 'failed'), "\n";

// pg_free_result() on an already-closed result throws on PHP 8.1+; both calls
// must return cleanly, the second reporting failure the way ext/pgsql used to.
echo 'FREE_FIRST=', var_export($db->free_result($result), true), "\n";
echo 'FREE_SECOND=', var_export($db->free_result($result), true), "\n";

// insert_id() reads the text of the last successful query.
$db->query('INSERT INTO '.$table.' (label) VALUES (\'first\')');
echo 'INSERT_ID=', var_export($db->insert_id(), true), "\n";
echo 'AFFECTED=', var_export($db->affected_rows(), true), "\n";

// A failed query must clear that text, so insert_id() cannot report the
// sequence of the INSERT that came before it.
$db->query('SELECT * FROM a_table_that_does_not_exist');
echo 'INSERT_ID_AFTER_FAILURE=', var_export($db->insert_id(), true), "\n";

// A later successful INSERT still reports its own id.
$db->query('INSERT INTO '.$table.' (label) VALUES (\'second\')');
echo 'INSERT_ID_AGAIN=', var_export($db->insert_id(), true), "\n";

$db->query('DROP TABLE '.$table);

// pg_close() on an already-closed connection throws on PHP 8.1+.
echo 'CLOSE_FIRST=', var_export($db->close(), true), "\n";
echo 'CLOSE_SECOND=', var_export($db->close(), true), "\n";

echo 'DONE';
