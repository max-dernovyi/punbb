<?php
/**
 * Drives the fixture extensions at one point, either through the bridge's
 * runner or through the legacy site the runner stands in for, and prints what
 * the caller received as BRIDGE=<json>.
 *
 * An entry point of a ScratchForum with punbb_fixture and punbb_fixture_dep
 * installed, served by forum_request_harness.php. $_GET['scenario'] names the
 * point, $_GET['via'] is "bridge" or "legacy"; the hooks' markers are tagged
 * bridge-<scenario>-<via>.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PunBB\Module\LegacyBridge\Hook\MarkupHookRunner;
use PunBB\Module\LegacyBridge\Hook\StatementHookRunner;

define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';


/** in_qr_get_cats_and_forums over index.php's query: the hook narrows it, query_build() runs what came back. */
function bridge_fixture_query($via)
{
	global $forum_container, $forum_db, $forum_user;

	$query = array(
		'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name',
		'FROM'		=> 'categories AS c',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'forums AS f',
				'ON'			=> 'c.id=f.cat_id'
			),
			array(
				'LEFT JOIN'		=> 'forum_perms AS fp',
				'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
			)
		),
		'WHERE'		=> 'fp.read_forum IS NULL OR fp.read_forum=1',
		'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
	);

	if ($via == 'bridge')
		$returned = $forum_container->get(StatementHookRunner::class)->run('in_qr_get_cats_and_forums', array('query' => &$query));
	else
		$returned = ($hook = get_hook('in_qr_get_cats_and_forums')) ? eval($hook) : null;

	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$forums = array();
	while ($row = $forum_db->fetch_assoc($result))
		$forums[] = $row['forum_name'];

	return array('returned' => $returned, 'query' => $query, 'forums' => $forums);
}


/** fn_get_remote_address_start: punbb_fixture returns the address header when there is one, punbb_fixture_dep sits behind it. */
function bridge_fixture_address($via)
{
	global $forum_container;

	if ($via == 'bridge')
		$returned = $forum_container->get(StatementHookRunner::class)->run('fn_get_remote_address_start', array());
	else
		$returned = ($hook = get_hook('fn_get_remote_address_start')) ? eval($hook) : null;

	return array('returned' => $returned);
}


/** vt_modify_topic_info: the hook reads the caller's $id and $cur_topic by name. */
function bridge_fixture_locals($via)
{
	global $forum_container;

	$id = 3;
	$cur_topic = array('forum_id' => 2, 'subject' => 'Bridged "topic" & more');

	if ($via == 'bridge')
		$returned = $forum_container->get(StatementHookRunner::class)->run('vt_modify_topic_info', array('id' => &$id, 'cur_topic' => &$cur_topic));
	else
		$returned = ($hook = get_hook('vt_modify_topic_info')) ? eval($hook) : null;

	return array('returned' => $returned, 'id' => $id, 'cur_topic' => $cur_topic);
}


/** in_main_output_start: the hook echoes a banner built through the point punbb_fixture offers and punbb_fixture_dep writes to. */
function bridge_fixture_banner($via)
{
	global $forum_container;

	ob_start();

	if ($via == 'bridge')
		$returned = $forum_container->get(StatementHookRunner::class)->run('in_main_output_start', array());
	else
		$returned = ($hook = get_hook('in_main_output_start')) ? eval($hook) : null;

	return array('returned' => $returned, 'html' => ob_get_clean());
}


/** vt_row_new_post_entry_data: a markup point between viewtopic.php's closing tags. */
function bridge_fixture_post($via)
{
	global $forum_container;

	$markup = $forum_container->get(MarkupHookRunner::class);
	$cur_post = array('id' => 7, 'poster' => 'admin');

	ob_start();

?>
					<div class="entry-content">
						<p>Post body</p>
					</div>
<?php if ($via == 'bridge'): echo $markup->render('vt_row_new_post_entry_data', array('cur_post' => &$cur_post)); else: ($hook = get_hook('vt_row_new_post_entry_data')) ? eval($hook) : null; endif; ?>
				</div>
<?php

	return array('html' => ob_get_clean());
}


$scenarios = array(
	'query'			=> 'bridge_fixture_query',
	'short_circuit'	=> 'bridge_fixture_address',
	'fall_through'	=> 'bridge_fixture_address',
	'locals'		=> 'bridge_fixture_locals',
	'banner'		=> 'bridge_fixture_banner',
	'post'			=> 'bridge_fixture_post'
);

$scenario = $_GET['scenario'] ?? '';
$via = $_GET['via'] ?? '';

if (!isset($scenarios[$scenario]) || !in_array($via, array('bridge', 'legacy'), true))
	exit('unknown scenario '.$scenario.' via '.$via);

// Set only now: the bootstrap reaches fn_get_remote_address_start as well.
$_SERVER['HTTP_X_PUNBB_FIXTURE_REQUEST'] = 'bridge-'.$scenario.'-'.$via;
if ($scenario == 'short_circuit')
	$_SERVER['HTTP_X_PUNBB_FIXTURE_ADDRESS'] = '198.51.100.7';

$report = $scenarios[$scenario]($via);
$report['stack'] = array_column($GLOBALS['ext_info_stack'] ?? array(), 'id');

echo "\n", 'BRIDGE=', json_encode($report), "\n";
