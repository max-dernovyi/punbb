<?php
/**
 * Calls get_hook() for every id of a hooks table and reports what came back.
 *
 * Out of process because the unit bootstrap defines FORUM_DISABLE_HOOKS.
 * $argv[1] is "enabled" or "disabled", $argv[2] a JSON file holding $forum_hooks.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');

if (($argv[1] ?? '') === 'disabled')
	define('FORUM_DISABLE_HOOKS', 1);

require FORUM_ROOT.'include/functions.php';

$forum_hooks = json_decode((string) file_get_contents($argv[2]), true);

$returned = array();
foreach (array_merge(array_keys($forum_hooks), array('punbb_fixture_unknown_point')) as $id)
	$returned[$id] = get_hook($id);

echo json_encode($returned);
