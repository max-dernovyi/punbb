<?php

declare(strict_types=1);

namespace PunBB\Module\AdminIndex\Model;

/**
 * What the machine the board runs on says about itself: its load and the PHP
 * build.
 */
class ServerEnvironment {
	/** @return list<float>|null the load averages over one, five and fifteen minutes; null where the machine does not tell */
	public function loadAverages(): ?array {
		$averages = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
		if (is_array($averages))
			return $averages;

		// @ because /proc is outside open_basedir on a lot of shared hosts, and the probe warning is not something the admin can act on
		$proc = @is_readable('/proc/loadavg') ? @file_get_contents('/proc/loadavg', false, null, 0, 64) : false;
		if (is_string($proc))
		{
			$averages = explode(' ', $proc);

			return isset($averages[2]) ? array_map(floatval(...), array_slice($averages, 0, 3)) : null;
		}

		if (!in_array(PHP_OS, array('WINNT', 'WIN32'), true) && function_exists('exec') && preg_match('/averages?: ([0-9\.]+),[\s]+([0-9\.]+),[\s]+([0-9\.]+)/i', (string) @exec('uptime'), $averages) === 1)
			return array((float) $averages[1], (float) $averages[2], (float) $averages[3]);

		return null;
	}

	/** @return array{string, string}|null the opcode cache's name and home page; null when none runs */
	public function accelerator(): ?array {
		return match (true) {
			function_exists('mmcache')							=> array('Turck MMCache', 'http://turck-mmcache.sourceforge.net/'),
			isset($GLOBALS['_PHPA'])							=> array('ionCube PHP Accelerator', 'http://www.php-accelerator.co.uk/'),
			(bool) ini_get('apc.enabled')						=> array('Alternative PHP Cache (APC)', 'http://www.php.net/apc/'),
			(bool) ini_get('zend_optimizer.optimization_level')	=> array('Zend Optimizer', 'http://www.zend.com/products/zend_optimizer/'),
			(bool) ini_get('eaccelerator.enable')				=> array('eAccelerator', 'http://eaccelerator.net/'),
			(bool) ini_get('xcache.cacher')						=> array('XCache', 'http://xcache.lighttpd.net/'),
			default												=> null,
		};
	}

	public function operatingSystem(): string {
		return PHP_OS;
	}

	public function phpVersion(): string {
		return PHP_VERSION;
	}

	/** Whether the server lets phpinfo() run. */
	public function allowsPhpInfo(): bool {
		return !str_contains(strtolower((string) ini_get('disable_functions')), 'phpinfo');
	}

	/** What phpinfo() prints. */
	public function phpInfo(): string {
		ob_start();
		phpinfo();

		return (string) ob_get_clean();
	}
}
