<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads the Composer autoloader and enough of the forum to exercise the
 * function-level tests. A full essentials.php bootstrap needs an installed
 * forum (config.php + a populated database); when that is missing the same
 * files are loaded without the database layer — see bootstrap_no_db.php.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

// PHPUnit includes the bootstrap from inside a method, so everything the forum
// code reaches through `global` has to be declared global here explicitly.
global $forum_db, $forum_config, $forum_user, $forum_flash, $forum_loader,
       $forum_hooks, $forum_start, $forum_url, $forum_updates, $forum_page,
       $lang_common, $tpl_main, $base_url;

define('FORUM_ROOT', dirname(__DIR__, 3).'/');

require FORUM_ROOT.'include/autoload.php';

if (!defined('FORUM_QUIET_VISIT'))
	define('FORUM_QUIET_VISIT', 1);

if (!defined('FORUM_DEBUG'))
	define('FORUM_DEBUG', 1);

if (file_exists(FORUM_ROOT.'config.php'))
	require_once FORUM_ROOT.'include/essentials.php';
else
	require_once __DIR__.'/bootstrap_no_db.php';

// parser.php only loads the IDNA class when this is set; without it the IDN
// tests silently exercise the unconverted path.
if (!defined('FORUM_ENABLE_IDNA'))
	define('FORUM_ENABLE_IDNA', 1);

require_once FORUM_ROOT.'lang/English/common.php';
require_once FORUM_ROOT.'include/parser.php';

forum_remove_bad_characters();
