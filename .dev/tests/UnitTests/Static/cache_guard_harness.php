<?php
/**
 * Includes a generated cache file in a fresh process and reports what ran.
 *
 * argv[1] is the file to include, argv[2] is "1" to define FORUM first — the
 * state the forum is always in when it includes a cache file, as opposed to a
 * direct HTTP request for one.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

if (isset($argv[2]) && $argv[2] === '1')
	define('FORUM', 1);

include $argv[1];

echo 'REACHED';
