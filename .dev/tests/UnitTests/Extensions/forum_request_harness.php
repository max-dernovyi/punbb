<?php
/**
 * Serves one request to an entry point of a ScratchForum, as the web server would.
 *
 * $argv[1] is the forum root, $argv[2] the script relative to it, $argv[3] a
 * JSON object with "get", "post" and "cookie". The script runs from its own
 * directory, so its relative FORUM_ROOT resolves inside the scratch root.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

list(, $root, $script, $request) = $argv;
$request = json_decode($request, true);

$_GET = $request['get'];
$_POST = $request['post'];
$_COOKIE = $request['cookie'];
$_REQUEST = $_GET + $_POST;

$query_string = http_build_query($_GET);

$_SERVER['REQUEST_METHOD'] = empty($_POST) ? 'GET' : 'POST';
$_SERVER['REQUEST_URI'] = '/'.$script.($query_string !== '' ? '?'.$query_string : '');
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/'.$script;
$_SERVER['QUERY_STRING'] = $query_string;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

chdir(dirname($root.'/'.$script));

require $root.'/'.$script;
