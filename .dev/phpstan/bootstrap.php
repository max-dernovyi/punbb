<?php
/**
 * PHPStan bootstrap.
 *
 * Makes the FORUM* constants and the shape of the forum globals known to the
 * analyser without connecting to a database or loading the whole forum.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 2).'/');
define('FORUM', 1);
define('FORUM_DEBUG', 1);
define('FORUM_QUIET_VISIT', 1);
define('FORUM_CACHE_DIR', FORUM_ROOT.'cache/');
define('FORUM_MAX_POSTSIZE_BYTES', 65535);
define('FORUM_SUPPORT_PCRE_UNICODE', 1);

require FORUM_ROOT.'include/constants.php';

/** @var mixed $forum_db DBLayer instance; the concrete class depends on the configured driver */
$forum_db = null;

/** @var array<string, string|null> $forum_config */
$forum_config = array();

/** @var array<string, mixed> $forum_user */
$forum_user = array();
