<?php
/**
 * Shipped code of the punbb_fixture test extension, required from its hooks.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

// Make sure no one attempts to run this script "directly"
if (!defined('FORUM'))
	exit;

define('PUNBB_FIXTURE_FUNCTIONS_LOADED', 1);


// Record that a hook fired and what it saw. The harness tags each request with
// X-Punbb-Fixture-Request, so the rows of one page are one query away.
function punbb_fixture_mark($extension_id, $hook_id, $seen)
{
	global $forum_db;

	$request_id = isset($_SERVER['HTTP_X_PUNBB_FIXTURE_REQUEST']) ? preg_replace('/[^0-9A-Za-z_-]/', '', substr($_SERVER['HTTP_X_PUNBB_FIXTURE_REQUEST'], 0, 40)) : '';

	$query = array(
		'INSERT'	=> 'request_id, extension_id, hook_id, seen',
		'INTO'		=> 'punbb_fixture_markers',
		'VALUES'	=> '\''.$forum_db->escape($request_id).'\', \''.$forum_db->escape($extension_id).'\', \''.$forum_db->escape($hook_id).'\', \''.$forum_db->escape(json_encode($seen, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)).'\''
	);

	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}


// Offers punbb_fixture_banner_pre_output, written the way core offers its points.
function punbb_fixture_banner()
{
	global $forum_config;

	$banner = array($forum_config['o_punbb_fixture_banner']);

	($hook = get_hook('punbb_fixture_banner_pre_output')) ? eval($hook) : null;

	return array(
		'parts'			=> $banner,
		// Once a nested hook pops itself, $ext_info names the outer extension again
		'ext_info_id'	=> isset($ext_info['id']) ? $ext_info['id'] : ''
	);
}
