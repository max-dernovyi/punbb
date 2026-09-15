<?php
/**
 * The front controller: the one script the web server runs.
 *
 * A path a module routes is served by its route. Any other path is a pretty
 * URL, which the rewrite rules of the forum's SEF scheme turn into a routed
 * one, or it is not found.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', './');
require FORUM_ROOT.'include/autoload.php';

// If query string is not set properly, create one and set $_GET
// E.g. lighttpd's 404 handler does not pass query string
if (empty($_SERVER['QUERY_STRING']) && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '?') !== false)
{
	$_SERVER['QUERY_STRING'] = (string) parse_url('http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'], PHP_URL_QUERY);
	parse_str($_SERVER['QUERY_STRING'], $_GET);
}

$forum_request = PunBB\Module\Framework\Http\Request::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
$forum_router = PunBB\Module\Framework\Modules\ModuleRegistry::discover(FORUM_ROOT.'include/PunBB/Module', 'PunBB\\Module\\')->router();
$forum_route = $forum_router->match($forum_request->path);

if ($forum_route === null)
{
	require FORUM_ROOT.'include/essentials.php';

	// Bring in all the rewrite rules
	if (file_exists(FORUM_ROOT.'include/url/'.$forum_config['o_sef'].'/rewrite_rules.php'))
		require FORUM_ROOT.'include/url/'.$forum_config['o_sef'].'/rewrite_rules.php';
	else
		require FORUM_ROOT.'include/url/Default/rewrite_rules.php';

	// Allow extensions to create their own rewrite rules/modify existing rules
	($hook = get_hook('re_rewrite_rules')) ? eval($hook) : null;

	$request_uri = $forum_request->path;
	$forum_rewrite = (new PunBB\Module\Framework\Routing\RewriteRules($forum_rewrite_rules))->rewrite($request_uri);
	$rewrite_target = $forum_rewrite !== null ? forum_rewrite_target($forum_rewrite->target) : false;
	$forum_route = $rewrite_target !== false ? $forum_router->match($rewrite_target) : null;

	if ($forum_route === null)
	{
		define('FORUM_HTTP_RESPONSE_CODE_SET', 1);
		header('HTTP/1.1 404 Not Found');

		// Allow an extension to override the "Bad request" message with a custom 404 page
		($hook = get_hook('re_page_not_found')) ? eval($hook) : null;

		error('Page Not found (Error 404):<br />The requested page <em>'.forum_htmlencode($request_uri).'</em> could not be found.');
	}

	// The page reads its parameters from the superglobals; $_REQUEST keeps what POST or COOKIE set
	foreach ($forum_rewrite->parameters as $rewrite_param => $rewrite_value)
	{
		if (!isset($_POST[$rewrite_param]) && !isset($_COOKIE[$rewrite_param]))
			$_REQUEST[$rewrite_param] = $rewrite_value;

		$_GET[$rewrite_param] = $rewrite_value;
	}

	$forum_request = $forum_request->rewritten($rewrite_target, $forum_rewrite->parameters);
}

// The name a page script had when the web server ran it directly
$_SERVER['PHP_SELF'] = $forum_request->base.$forum_route->paths[0];

if ($forum_route->setup)
{
	require FORUM_ROOT.'include/setup.php';

	// The controller reads the parameters include/setup.php cleaned
	$forum_request = $forum_request->withParameters($_GET, $_POST, $_COOKIE);

	$forum_container->get(PunBB\Module\Framework\Routing\FrontController::class)->handle($forum_route, $forum_request)->send();
}
else
{
	if ($forum_route->isQuietFor($forum_request) && !defined('FORUM_QUIET_VISIT'))
		define('FORUM_QUIET_VISIT', 1);

	// A controller checking the token of a POST itself opts out of the gate include/common.php runs
	if ($forum_route->checksOwnToken && !defined('FORUM_SKIP_CSRF_CONFIRM'))
		define('FORUM_SKIP_CSRF_CONFIRM', 1);

	require FORUM_ROOT.'include/common.php';

	// The controller reads the parameters include/common.php cleaned, as a page script read the superglobals
	$forum_request = $forum_request->withParameters($_GET, $_POST, $_COOKIE);

	forum_send_response($forum_container->get(PunBB\Module\Framework\Routing\FrontController::class)->handle($forum_route, $forum_request));
}
