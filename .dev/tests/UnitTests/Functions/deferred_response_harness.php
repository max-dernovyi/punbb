<?php
/**
 * Ends a page through forum_end_page() in a fresh process: it exits, so only a
 * separate process can observe what reached the output and in which order.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

require __DIR__.'/../TestHelper.php';

$GLOBALS['forum_db'] = new class {
	public function end_transaction(): void { echo '[end transaction]'; }
	public function close(): void { echo '[close]'; }
};

ini_set('error_log', '/dev/stdout');

if (($argv[1] ?? '') === 'deferred')
{
	forum_defer(static function (): void { echo '[job one, seeing '.(defined('FORUM_RESPONSE_SENT') ? 'the response sent' : 'the response pending').']'; throw new RuntimeException('probe failure'); });
	forum_defer(static function (): void { echo '[job two]'; });
}

ob_start();
echo '[printed early]';

forum_end_page('[body]');
