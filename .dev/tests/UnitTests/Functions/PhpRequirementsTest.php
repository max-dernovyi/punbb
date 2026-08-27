<?php
/**
 * The PHP 8.4 requirement: the version gate, the extension checks and the
 * dead PHP 4/5 branches that used to guard them.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhpRequirementsTest extends TestCase {
	private const OK_EXTENSIONS = array('mbstring', 'intl', 'json', 'xml', 'pcre');
	private const OK_DB_TYPES = array('mysqli', 'sqlite3');

	private function source(string $file): string {
		return file_get_contents(FORUM_ROOT.$file);
	}

	public function testMinimumVersionIsPhp84(): void {
		$this->assertSame('8.4.0', FORUM_MIN_PHP_VERSION);
	}

	public function testInstallAndUpdateGatesMatchTheConstant(): void {
		$this->assertStringContainsString(
			'define(\'MIN_PHP_VERSION\', \''.FORUM_MIN_PHP_VERSION.'\');',
			$this->source('admin/install.php')
		);
		$this->assertStringContainsString(
			'version_compare(PHP_VERSION, \''.FORUM_MIN_PHP_VERSION.'\', \'<\')',
			$this->source('admin/db_update.php')
		);
	}

	public function testAMetEnvironmentReportsNothing(): void {
		$errors = forum_requirement_errors('8.4.0', self::OK_EXTENSIONS, self::OK_DB_TYPES);

		$this->assertSame(array(), $errors);
	}

	public function testThisInstallationMeetsTheRequirements(): void {
		$this->assertSame(array(), check_php_requirements());
	}

	#[DataProvider('tooOldVersionProvider')]
	public function testAnOlderPhpIsRejected(string $version): void {
		$errors = forum_requirement_errors($version, self::OK_EXTENSIONS, self::OK_DB_TYPES);

		$this->assertCount(1, $errors);
		$this->assertStringContainsString('requires at least PHP 8.4.0', $errors[0]);
		$this->assertStringContainsString($version, $errors[0]);
	}

	/** @return list<array{string}> */
	public static function tooOldVersionProvider(): array {
		return array(array('5.6.40'), array('7.4.33'), array('8.0.30'), array('8.3.99'));
	}

	#[DataProvider('newEnoughVersionProvider')]
	public function testTheMinimumAndNewerAreAccepted(string $version): void {
		$this->assertSame(array(), forum_requirement_errors($version, self::OK_EXTENSIONS, self::OK_DB_TYPES));
	}

	/** @return list<array{string}> */
	public static function newEnoughVersionProvider(): array {
		return array(array('8.4.0'), array('8.4.24'), array('8.5.0'), array('9.0.0'));
	}

	public function testAMissingExtensionIsNamed(): void {
		$loaded = array_values(array_diff(self::OK_EXTENSIONS, array('intl')));
		$errors = forum_requirement_errors('8.4.0', $loaded, self::OK_DB_TYPES);

		$this->assertCount(1, $errors);
		$this->assertStringContainsString('extensions are not loaded: intl', $errors[0]);
	}

	#[DataProvider('requiredExtensionProvider')]
	public function testInstallAbortsWhenARequiredExtensionIsMissing(string $extension): void {
		$loaded = array_values(array_diff(self::OK_EXTENSIONS, array($extension)));
		$errors = forum_requirement_errors('8.4.0', $loaded, self::OK_DB_TYPES);

		$this->assertCount(1, $errors);
		$this->assertStringContainsString('extensions are not loaded: '.$extension, $errors[0]);
	}

	/** @return list<array{string}> */
	public static function requiredExtensionProvider(): array {
		return array_map(static fn(string $e): array => array($e), forum_required_extensions());
	}

	public function testMbstringAndIntlAreHardRequirements(): void {
		$required = forum_required_extensions();

		$this->assertContains('mbstring', $required, 'include/utf8.php is built on mb_*');
		$this->assertContains('intl', $required, 'forum_idna_* is built on idn_to_ascii()');
	}

	public function testEveryMissingExtensionIsListedInOneError(): void {
		$errors = forum_requirement_errors('8.4.0', array('pcre'), self::OK_DB_TYPES);

		$this->assertCount(1, $errors);
		foreach (forum_required_extensions() as $extension)
			$this->assertStringContainsString($extension, $errors[0]);
	}

	public function testExtensionNamesAreMatchedCaseInsensitively(): void {
		$errors = forum_requirement_errors('8.4.0', array('MBString', 'Intl', 'JSON', 'XML'), self::OK_DB_TYPES);

		$this->assertSame(array(), $errors);
	}

	public function testNoDatabaseExtensionIsRejected(): void {
		$errors = forum_requirement_errors('8.4.0', self::OK_EXTENSIONS, array());

		$this->assertCount(1, $errors);
		$this->assertStringContainsString('None of the supported database extensions', $errors[0]);
		foreach (forum_supported_db_types() as $db_type)
			$this->assertStringContainsString($db_type, $errors[0]);
	}

	public function testEveryUnmetRequirementIsReported(): void {
		$errors = forum_requirement_errors('7.4.33', array(), array());

		$this->assertCount(3, $errors);
	}

	public function testAvailableDriversAreASubsetOfTheSupportedOnes(): void {
		$available = forum_available_db_types();

		$this->assertSame(array(), array_diff($available, forum_supported_db_types()));
		$this->assertNotEmpty($available, 'the test environment has no database extension');
	}

	#[DataProvider('entryPointProvider')]
	public function testEntryPointsCheckTheRequirements(string $file): void {
		$this->assertStringContainsString('check_php_requirements()', $this->source($file));
	}

	/** @return list<array{string}> */
	public static function entryPointProvider(): array {
		return array(array('admin/install.php'), array('admin/db_update.php'));
	}

	public function testDeadPhp5GatesAreCollapsed(): void {
		$this->assertStringNotContainsString(
			'version_compare(PHP_VERSION, \'5.',
			$this->source('include/functions.php').$this->source('include/essentials.php').$this->source('admin/db_update.php'),
			'PHP 5 version branches are dead code on 8.4'
		);
	}

	public function testExtensionManifestChecksAreUntouched(): void {
		$xml = $this->source('include/xml.php');

		$this->assertStringContainsString('$ext[\'minphpversion\']', $xml);
		$this->assertStringContainsString('$ext[\'maxphpversion\']', $xml);
	}

	public function testPcreUnicodeProbeKeepsOnlyTheModernBranch(): void {
		$essentials = $this->source('include/essentials.php');

		$this->assertStringContainsString('FORUM_SUPPORT_PCRE_UNICODE', $essentials);
		$this->assertStringNotContainsString('5.0.0-dev', $essentials);
		$this->assertTrue(defined('FORUM_SUPPORT_PCRE_UNICODE'), 'PCRE on 8.4 always supports \p{L}');
	}

	/** @return array<string, array{string}> */
	public static function independentBootstraps(): array {
		return array(
			'install' => array('admin/install.php'),
			'db_update' => array('admin/db_update.php'),
		);
	}

	/**
	 * include/utf8.php calls mb_internal_encoding() at load time, so a host
	 * without mbstring has to be turned away before the require, not after.
	 */
	#[DataProvider('independentBootstraps')]
	public function testRequirementCheckRunsBeforeTheUtf8Loader(string $file): void {
		$source = $this->source($file);

		$check = strpos($source, 'check_php_requirements()');
		$utf8 = strpos($source, "require FORUM_ROOT.'include/utf8.php'");

		$this->assertIsInt($check, $file.' must call check_php_requirements()');
		$this->assertIsInt($utf8, $file.' must require include/utf8.php');
		$this->assertLessThan($utf8, $check, $file.' checks the requirements after loading utf8.php');
	}

	public function testGetRemoteFileKeepsOnlyTheStreamContextPath(): void {
		$source = $this->source('include/functions.php');
		$body = substr($source, strpos($source, 'function get_remote_file'));
		$body = substr($body, 0, strpos($body, "\n}"));

		$this->assertStringContainsString('stream_context_create', $body);
		$this->assertStringNotContainsString('@file_get_contents($url)', $body, 'the context-less fallback is PHP 4 only');
	}
}
