<?php
/**
 * Runs include/dblayer/common_db.php for one $db_type in a fresh process.
 *
 * The dispatcher's failure branches call error(), which prints a page and
 * exits, so they can only be observed out of process.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');
define('FORUM', 1);
define('FORUM_DEBUG', 1);

require FORUM_ROOT.'include/constants.php';
require FORUM_ROOT.'include/functions.php';

$db_type = isset($argv[1]) ? $argv[1] : '';
$db_host = $db_username = $db_password = $db_name = $db_prefix = '';
$p_connect = false;

require FORUM_ROOT.'include/dblayer/common_db.php';

echo 'DISPATCHED';
