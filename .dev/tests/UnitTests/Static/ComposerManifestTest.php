<?php
/**
 * The Composer manifest as a runtime contract: the extensions the forum calls
 * into must be declared in `require`, and the vendor tree must stay out of the
 * repository while the lock file stays in it.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ComposerManifestTest extends TestCase {
	// "8.4" and "8.4.0" describe the same release; compare them padded.
	private function normalise(string $version): string {
		return implode('.', array_pad(explode('.', $version), 3, '0'));
	}

	/** @return array<string, mixed> */
	private function manifest(): array {
		return json_decode(file_get_contents(FORUM_ROOT.'composer.json'), true);
	}

	public function testTheManifestIsValidJson(): void {
		$this->assertIsArray($this->manifest());
	}

	#[DataProvider('requiredExtensionProvider')]
	public function testEveryRequiredExtensionIsDeclared(string $extension): void {
		$require = $this->manifest()['require'];

		$this->assertArrayHasKey('ext-'.$extension, $require);
	}

	/** @return list<array{string}> */
	public static function requiredExtensionProvider(): array {
		return array_map(static fn(string $e): array => array($e), forum_required_extensions());
	}

	public function testTheDeclaredExtensionsAreExactlyTheRequiredOnes(): void {
		$declared = array();
		foreach (array_keys($this->manifest()['require']) as $package)
			if (str_starts_with($package, 'ext-'))
				$declared[] = substr($package, 4);

		sort($declared);
		$required = forum_required_extensions();
		sort($required);

		$this->assertSame($required, $declared);
	}

	public function testThePhpConstraintMatchesTheMinimumVersion(): void {
		$constraint = $this->manifest()['require']['php'];

		$this->assertStringStartsWith('>=', $constraint);
		$this->assertSame(
			$this->normalise(FORUM_MIN_PHP_VERSION),
			$this->normalise(substr($constraint, 2)),
			'composer.json requires PHP '.$constraint.', the forum gate is '.FORUM_MIN_PHP_VERSION
		);
	}

	public function testThePlatformOverrideMatchesTheMinimumVersion(): void {
		$platform = $this->manifest()['config']['platform']['php'];

		$this->assertSame($this->normalise(FORUM_MIN_PHP_VERSION), $this->normalise($platform));
	}

	public function testTheLockFileIsPresentAndDescribesTheDevDependencies(): void {
		$lock = json_decode(file_get_contents(FORUM_ROOT.'composer.lock'), true);

		$this->assertIsArray($lock);
		$this->assertNotEmpty($lock['content-hash']);
		$this->assertSame(array(), $lock['packages'], 'the forum itself pulls in no runtime package');

		$locked = array_column($lock['packages-dev'], 'name');
		foreach (array_keys($this->manifest()['require-dev']) as $package)
			$this->assertContains($package, $locked);
	}

	public function testTheVendorTreeIsIgnoredAndTheLockFileIsNot(): void {
		$ignored = array_map('trim', file(FORUM_ROOT.'.gitignore'));

		$this->assertContains('/vendor/', $ignored);
		$this->assertNotContains('composer.lock', $ignored);
	}

	public function testTheVendorTreeIsNeverServed(): void {
		$scripts = $this->manifest()['scripts'];

		$this->assertArrayHasKey('post-install-cmd', $scripts);
		$this->assertArrayHasKey('post-update-cmd', $scripts);
		$this->assertStringContainsString('vendor/.htaccess', $scripts['protect-vendor']);
	}

	#[DataProvider('composerScriptProvider')]
	public function testTheCheckGateScriptsExist(string $script): void {
		$this->assertArrayHasKey($script, $this->manifest()['scripts']);
	}

	/** @return list<array{string}> */
	public static function composerScriptProvider(): array {
		return array(array('lint'), array('stan'), array('test'), array('smoke'));
	}

	#[DataProvider('devDependencyProvider')]
	public function testDevDependenciesAreOnTheirCurrentStableMajor(string $package, string $major): void {
		$this->assertSame($major, $this->manifest()['require-dev'][$package]);
	}

	/** @return list<array{string, string}> */
	public static function devDependencyProvider(): array {
		return array(
			array('php-parallel-lint/php-parallel-lint', '^1.4'),
			array('phpstan/phpstan', '^2'),
			array('phpunit/phpunit', '^12'),
		);
	}
}
