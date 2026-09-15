<?php
/**
 * Composes the board as it runs once include/PunBB/Module/Install/ and Update/
 * are deleted: their modules are not discovered and none of their classes loads.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');

require FORUM_ROOT.'include/autoload.php';

spl_autoload_register(static function (string $class): void {
	if (preg_match('/\APunBB\\\\Module\\\\(?:Install|Update)\\\\/', $class) === 1)
		throw new LogicException('the board loaded '.$class);
}, true, true);

$modules = array();
foreach (glob(FORUM_ROOT.'include/PunBB/Module/*/Module.php') ?: array() as $file)
{
	$name = basename(dirname($file));
	if ($name !== 'Install' && $name !== 'Update')
		$modules[] = new ('PunBB\\Module\\'.$name.'\\Module')();
}

$registry = new PunBB\Module\Framework\Modules\ModuleRegistry(...$modules);
$router = $registry->router();

echo implode(' ', array(
	in_array('LegacyBridge', $registry->names(), true) ? 'bridge' : 'no bridge',
	$router->match('index.php') !== null ? 'index' : 'no index',
	$router->match('admin/db_update.php') === null ? 'no updater' : 'updater',
	$router->match('admin/install.php') === null ? 'no installer' : 'installer',
	$registry->container()->has(PunBB\Module\Framework\Event\EventDispatcher::class) ? 'events' : 'no events',
));
