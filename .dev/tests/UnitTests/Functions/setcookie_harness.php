<?php
/**
 * Prints the headers forum_setcookie() emits, as JSON.
 *
 * Served by PHP's built-in web server: the CLI SAPI discards headers, so the
 * only way to observe the real Set-Cookie output is over HTTP.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');
define('FORUM_DISABLE_HOOKS', 1);

require FORUM_ROOT.'include/functions.php';

$cookie_path = isset($_GET['path']) ? $_GET['path'] : '/';
$cookie_domain = isset($_GET['domain']) ? $_GET['domain'] : '';
$cookie_secure = isset($_GET['secure']) ? (int)$_GET['secure'] : 0;

forum_setcookie('forum_cookie_test', 'a value', 1893456000);

header('Content-Type: application/json');
echo json_encode(headers_list());
