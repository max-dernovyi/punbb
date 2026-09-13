<?php
/**
 * Reaches both kinds of deprecated entry from two lines each — the bridge's
 * runner and a legacy point with punbb_fixture's code attached — then prints
 * a page body.
 *
 * An entry point of a ScratchForum, served by forum_request_harness.php.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PunBB\Module\LegacyBridge\Hook\StatementHookRunner;

define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

$id = 3;
$cur_topic = array('forum_id' => 2, 'subject' => 'Topic');
$runner = $forum_container->get(StatementHookRunner::class);

($hook = get_hook('vt_modify_topic_info')) ? eval($hook) : null; // hook: first
($hook = get_hook('vt_modify_topic_info')) ? eval($hook) : null; // hook: second

$runner->run('vt_modify_topic_info', array('id' => &$id, 'cur_topic' => &$cur_topic)); // run: first
$runner->run('vt_modify_topic_info', array('id' => &$id, 'cur_topic' => &$cur_topic)); // run: second

echo '<p id="harness-page">page</p>', "\n";
