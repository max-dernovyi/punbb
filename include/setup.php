<?php
/**
 * Loads what the installer and the updater run on: they run before a usable
 * configuration exists, so nothing of the board is loaded or connected here.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */


if (!defined('FORUM_ROOT'))
	exit('The constant FORUM_ROOT must be defined and point to a valid PunBB installation root directory.');

require FORUM_ROOT.'include/autoload.php';
require FORUM_ROOT.'include/constants.php';
require FORUM_ROOT.'include/functions.php';

// config.php defines FORUM for the updater; the installer runs without one, and reports what a database refuses
if (!file_exists(FORUM_ROOT.'config.php'))
{
	define('FORUM', 1);
	define('FORUM_DEBUG', 1);
}

// A deprecation notice goes to the log, never onto a page, as essentials.php routes it
set_error_handler('forum_log_deprecation', E_USER_DEPRECATED);

// include/utf8.php calls mb_internal_encoding(), which fatals without mbstring: the page names what is missing instead
if (check_php_requirements() === array())
{
	require FORUM_ROOT.'include/utf8.php';

	// Strip out "bad" UTF-8 characters
	forum_remove_bad_characters();
}

error_reporting(E_ALL);

// Turn off PHP time limit
if (function_exists('set_time_limit'))
	set_time_limit(0);

// The composition root of a setup route: nothing past this point is the board's
$forum_container = PunBB\Module\Framework\Modules\ModuleRegistry::forum(FORUM_ROOT, error_log(...))->container();
