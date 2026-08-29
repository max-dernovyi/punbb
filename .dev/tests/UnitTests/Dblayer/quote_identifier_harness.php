<?php
/**
 * Prints a driver's identifier quoting, without a server.
 *
 * Out of process because every driver declares the same class DBLayer. The
 * object is built without the constructor: quoting is pure string work and
 * must not need a connection.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');
define('FORUM', 1);

require FORUM_ROOT.'include/dblayer/'.$argv[1].'.php';

$db = (new ReflectionClass('DBLayer'))->newInstanceWithoutConstructor();

echo 'PLAIN='.$db->quote_identifier('rank')."\n";
echo 'RESERVED_TABLE='.$db->quote_identifier('groups')."\n";
echo 'NORMAL='.$db->quote_identifier('username')."\n";
echo 'ESCAPED='.$db->quote_identifier('we`ird"one')."\n";
echo "DONE\n";
