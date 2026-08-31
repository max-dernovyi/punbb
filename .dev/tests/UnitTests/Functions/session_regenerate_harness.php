<?php
/**
 * Prints what forum_session_regenerate() did to a running session.
 *
 * session_regenerate_id(true) needs a real session, which PHPUnit's process
 * does not have, so the case the fix exists for runs out of process.
 *
 * Usage: php session_regenerate_harness.php <save_path>
 * Prints "<old id> <new id> <old file still there: 1|0>".
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');

require FORUM_ROOT.'include/functions.php';

$forum_hooks = array();

session_save_path($argv[1]);
session_start();
$_SESSION['carried'] = 'kept';

$old = session_id();

forum_session_regenerate();

$new = session_id();

echo $old.' '.$new.' '.(file_exists($argv[1].'/sess_'.$old) ? '1' : '0').' '.($_SESSION['carried'] ?? '');
