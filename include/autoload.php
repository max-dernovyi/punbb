<?php
/**
 * Loads the Composer autoloader.
 *
 * Required from every entry point that does not go through essentials.php.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

if (!defined('FORUM_ROOT'))
	exit('The constant FORUM_ROOT must be defined and point to a valid PunBB installation root directory.');

if (!file_exists(FORUM_ROOT.'vendor/autoload.php'))
	exit('Dependencies are not installed. Run \'composer install\' in the PunBB root directory.');

require_once FORUM_ROOT.'vendor/autoload.php';
