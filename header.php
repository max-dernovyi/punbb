<?php
/**
 * The chrome of a page still built on $tpl_main, rendered by the layout: the
 * template comes back with the header's regions filled and the markers of the
 * page's own regions left for the page to fill.
 *
 * Deprecated since 2.0: a page renders through the layout. v2.1 removes it.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */


// Make sure no one attempts to run this script "directly"
if (!defined('FORUM'))
	exit;

trigger_error('Including header.php is deprecated since 2.0, render the page through PunBB\Module\Layout\Chrome\Layout', E_USER_DEPRECATED);

// Included from a function's scope too, as message() does, and from extension code, where message() still reads the global
$GLOBALS['tpl_main'] = $GLOBALS['forum_container']->get(PunBB\Module\LegacyBridge\Layout\TemplateProtocol::class)->header();
$tpl_main = &$GLOBALS['tpl_main'];

// The administration's index lists the alerts the header raised
$alert_items = $GLOBALS['forum_container']->get(PunBB\Module\LegacyBridge\Layout\TemplateProtocol::class)->alertItems();
