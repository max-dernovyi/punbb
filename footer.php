<?php
/**
 * Ends a page still built on $tpl_main: the layout fills the footer's regions
 * into the template, and the page is sent.
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

trigger_error('Including footer.php is deprecated since 2.0, render the page through PunBB\Module\Layout\Chrome\Layout', E_USER_DEPRECATED);

// A page that never included header.php has no template, and gets an empty one
forum_end_page($GLOBALS['forum_container']->get(PunBB\Module\LegacyBridge\Layout\TemplateProtocol::class)->footer(isset($tpl_main) ? $tpl_main : ''));
