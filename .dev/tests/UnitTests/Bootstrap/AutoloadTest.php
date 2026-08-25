<?php

use PHPUnit\Framework\TestCase;

class AutoloadTest extends TestCase {
	public function testComposerAutoloaderIsLoaded(): void {
		$this->assertTrue(class_exists(\Composer\Autoload\ClassLoader::class));
	}

	public function testEveryBootstrapEntryPointRequiresTheAutoloader(): void {
		$root = dirname(__FILE__).'/../../../../';
		$entry_points = array('include/essentials.php', 'admin/install.php', 'admin/db_update.php');

		foreach ($entry_points as $entry_point)
			$this->assertStringContainsString(
				'include/autoload.php',
				file_get_contents($root.$entry_point),
				$entry_point.' must require the Composer autoloader'
			);
	}
}
