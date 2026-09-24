<?php
/**
 * Fetches one URL through the real get_remote_file() and prints what it
 * returned, then what LegacyEnvironment::fetchesRemoteFiles() says.
 *
 * Out of process, because the server a test points it at answers from another
 * process while this one waits, and because the transport and the trusted CA
 * are php.ini settings. $argv[1] is a JSON object: url, timeout, head_only.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');
define('FORUM', 1);

$options = json_decode($argv[1], true) + array('timeout' => 2, 'head_only' => false);

require FORUM_ROOT.'include/autoload.php';
require FORUM_ROOT.'include/constants.php';
require FORUM_ROOT.'include/functions.php';

echo 'RESULT=', json_encode(get_remote_file($options['url'], $options['timeout'], $options['head_only'])), "\n";
echo 'FETCHES=', var_export((new \PunBB\Module\LegacyBridge\Setup\LegacyEnvironment())->fetchesRemoteFiles(), true), "\n";
