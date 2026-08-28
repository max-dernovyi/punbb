<?php
/**
 * Renders JS with the Loader's hooks live and prints the result.
 *
 * Out of process because the unit bootstrap defines FORUM_DISABLE_HOOKS, so
 * get_hook() can never fire in the suite itself. $argv[1] is the case.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');
define('FORUM', 1);

require FORUM_ROOT.'include/constants.php';
require FORUM_ROOT.'include/functions.php';
require FORUM_ROOT.'include/loader.php';
require FORUM_ROOT.'include/utf8.php';

$forum_hooks = array();
$loader = Loader::singleton();

switch ($argv[1])
{
	// A _start hook returning a value replaces the renderer's output.
	case 'start':
		$forum_hooks['ld_fn_render_js_simple_start'] = array('return "hooked";');
		$loader->add_js('http://localhost/a.js');
		break;

	// An _end hook sees $output and can amend it.
	case 'end':
		$forum_hooks['ld_fn_render_js_simple_end'] = array('$output .= "<!-- end -->";');
		$loader->add_js('http://localhost/a.js');
		break;

	// A hook disabling a lib by nulling its data must not emit <script src="">.
	case 'disabled_lib':
		$forum_hooks['ld_fn_render_js_start'] = array('$this->libs[\'js\'][\'http://localhost/a.js\'][\'data\'] = FALSE;');
		$loader->add_js('http://localhost/a.js');
		$loader->add_js('http://localhost/b.js');
		break;

	default:
		exit('unknown case '.$argv[1]);
}

echo $loader->render_js();
