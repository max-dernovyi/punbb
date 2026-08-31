<?php
/**
 * Prints the destination redirect() would send the browser to.
 *
 * redirect() ends the request with a rendered page, so the only way to read
 * its normalised destination is from inside it. The hook it already fires
 * after the destination is final does exactly that.
 *
 * Usage: php redirect_harness.php <base_url> <destination>
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');

require FORUM_ROOT.'include/functions.php';

$base_url = $argv[1];
$forum_config = array('o_redirect_delay' => '1');
$forum_user = array('style' => 'Oxygen');
$lang_common = array();

$forum_hooks = array(
	'fn_redirect_pre_template_loaded' => array('echo $destination_url; exit;'),
);

redirect($argv[2], '');
