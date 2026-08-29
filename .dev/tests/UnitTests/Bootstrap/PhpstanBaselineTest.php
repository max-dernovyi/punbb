<?php

use PHPUnit\Framework\TestCase;

class PhpstanBaselineTest extends TestCase {
	private const ROOT = __DIR__.'/../../../../';

	public function testBaselineExists(): void {
		$this->assertFileExists(self::ROOT.'phpstan-baseline.neon');
	}

	public function testBaselineIsReferencedFromTheConfig(): void {
		$this->assertStringContainsString(
			'phpstan-baseline.neon',
			file_get_contents(self::ROOT.'phpstan.neon'),
			'phpstan.neon must include phpstan-baseline.neon'
		);
	}
}
