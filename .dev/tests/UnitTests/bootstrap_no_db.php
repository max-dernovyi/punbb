<?php
/**
 * Database-less variant of include/essentials.php for the unit test suite.
 *
 * Mirrors what essentials.php loads, minus the database layer, the config
 * cache and the hooks cache. $forum_config holds the installer defaults for
 * the options the tested functions read.
 *
 * Always used, never conditionally: the unit suite must not change behaviour
 * because the checkout happens to have a forum installed in it.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

if (!defined('FORUM_ROOT'))
	exit('The constant FORUM_ROOT must be defined and point to a valid PunBB installation root directory.');

define('FORUM', 1);
define('FORUM_DISABLE_HOOKS', 1);

require FORUM_ROOT.'include/constants.php';

list($usec, $sec) = explode(' ', microtime());
$forum_start = ((float)$usec + (float)$sec);

require FORUM_ROOT.'include/functions.php';
require FORUM_ROOT.'include/loader.php';

require FORUM_ROOT.'include/utf8/utf8.php';
require FORUM_ROOT.'include/utf8/ucwords.php';
require FORUM_ROOT.'include/utf8/trim.php';

error_reporting(E_ALL);

if (@preg_match('/\p{L}/u', 'a') !== false)
	define('FORUM_SUPPORT_PCRE_UNICODE', 1);

// Force POSIX locale (to prevent functions such as strtolower() from messing up UTF-8 strings)
setlocale(LC_CTYPE, 'C');

if (!defined('FORUM_CACHE_DIR'))
	define('FORUM_CACHE_DIR', FORUM_ROOT.'cache/');

$base_url = 'http://localhost';

$forum_config = array(
	'o_avatars_dir'			=> 'img/avatars',
	'o_censoring'			=> '0',
	'o_indent_num_spaces'	=> '4',
	'o_make_links'			=> '1',
	'o_quote_depth'			=> '3',
	'o_smilies'				=> '1',
	'o_smilies_sig'			=> '1',
	'p_message_bbcode'		=> '1',
	'p_message_img_tag'		=> '1',
	'p_sig_bbcode'			=> '1',
	'p_sig_img_tag'			=> '0',
);

$forum_user = array(
	'is_guest'			=> '1',
	'show_img'			=> '1',
	'show_img_sig'		=> '1',
	'show_smilies'		=> '1',
);

require FORUM_ROOT.'include/flash_messenger.php';
$forum_flash = new FlashMessenger();

if (!defined('FORUM_MAX_POSTSIZE_BYTES'))
	define('FORUM_MAX_POSTSIZE_BYTES', 65535);

define('FORUM_ESSENTIALS_LOADED', 1);
