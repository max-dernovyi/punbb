<?php
/**
 * Every property a class touches must be declared (PHP 8.2 deprecates the
 * creation of dynamic properties, and 9.0 will make it an error).
 *
 * The check is source-level because the four drivers all declare the same
 * class DBLayer and cannot be loaded into one process; a token scan reads all
 * of them at once and fails the build the moment an undeclared $this->x
 * appears. include/idna/ is replaced by a later step and is deliberately out of scope.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class DeclaredPropertiesTest extends TestCase {
	public static function classFiles(): array {
		$files = array(
			'include/dblayer/mysqli.php',
			'include/dblayer/mysqli_innodb.php',
			'include/dblayer/pgsql.php',
			'include/dblayer/sqlite3.php',
			'include/loader.php',
			'include/flash_messenger.php'
		);

		$cases = array();
		foreach ($files as $file)
			$cases[$file] = array($file);

		return $cases;
	}

	/**
	 * Names declared as properties in $source.
	 *
	 * @return array<string, true>
	 */
	private function declared(string $source): array {
		$declared = array();

		if (preg_match_all('/^\s*(?:public|protected|private|var)\s+(?:static\s+)?(?:readonly\s+)?(?:\??[A-Za-z_\\\\][A-Za-z0-9_\\\\|]*\s+)?\$([A-Za-z_][A-Za-z0-9_]*)/m', $source, $matches))
			foreach ($matches[1] as $name)
				$declared[$name] = true;

		return $declared;
	}

	/**
	 * Every $this->name the source touches, method calls excluded.
	 *
	 * @return array<string, int> name => line of the first use
	 */
	private function touched(string $source): array {
		$tokens = token_get_all($source);
		$touched = array();

		$significant = array();
		foreach ($tokens as $index => $token)
			if (!is_array($token) || !in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true))
				$significant[] = $index;

		foreach ($significant as $position => $index)
		{
			$token = $tokens[$index];
			if (!is_array($token) || $token[0] !== T_VARIABLE || $token[1] !== '$this')
				continue;

			$arrow = $tokens[$significant[$position + 1]] ?? null;
			if (!is_array($arrow) || ($arrow[0] !== T_OBJECT_OPERATOR && $arrow[0] !== T_NULLSAFE_OBJECT_OPERATOR))
				continue;

			$name = $tokens[$significant[$position + 2]] ?? null;
			if (!is_array($name) || $name[0] !== T_STRING)
				continue;

			// $this->foo( is a method call, not a property.
			if (($tokens[$significant[$position + 3]] ?? null) === '(')
				continue;

			if (!isset($touched[$name[1]]))
				$touched[$name[1]] = $name[2];
		}

		return $touched;
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('classFiles')]
	public function testEveryTouchedPropertyIsDeclared(string $file): void {
		$source = file_get_contents(FORUM_ROOT.$file);
		$declared = $this->declared($source);

		$undeclared = array();
		foreach ($this->touched($source) as $name => $line)
			if (!isset($declared[$name]))
				$undeclared[] = $file.':'.$line.' $this->'.$name;

		$this->assertSame(array(), $undeclared, 'undeclared property: '.implode(', ', $undeclared));
	}

	/** The scanner has to see a dynamic property, or it guards nothing. */
	public function testTheScannerFindsAnUndeclaredProperty(): void {
		$source = "<?php\nclass C {\n\tpublic \$declared;\n\tfunction f() {\n\t\t\$this->declared = 1;\n\t\t\$this->dynamic = 2;\n\t\t\$this->method();\n\t}\n}\n";

		$this->assertArrayHasKey('declared', $this->declared($source));
		$this->assertArrayNotHasKey('dynamic', $this->declared($source));
		$this->assertArrayHasKey('dynamic', $this->touched($source));
		$this->assertArrayNotHasKey('method', $this->touched($source));
	}

	/** PHP 4 property syntax: `var $x` still works, but nothing new should use it. */
	#[\PHPUnit\Framework\Attributes\DataProvider('classFiles')]
	public function testNoPhp4VarDeclarationsRemain(string $file): void {
		$source = file_get_contents(FORUM_ROOT.$file);

		$this->assertSame(0, preg_match('/^\s*var\s+\$/m', $source), $file.' still declares properties with `var`');
	}

	/** A blanket #[\AllowDynamicProperties] would defeat the whole pass. */
	#[\PHPUnit\Framework\Attributes\DataProvider('classFiles')]
	public function testNoClassOptsOutOfTheDeprecation(string $file): void {
		$this->assertStringNotContainsString('AllowDynamicProperties', file_get_contents(FORUM_ROOT.$file), $file);
	}

	public static function drivers(): array {
		return array(
			'mysqli' => array('mysqli'),
			'mysqli_innodb' => array('mysqli_innodb'),
			'pgsql' => array('pgsql'),
			'sqlite3' => array('sqlite3')
		);
	}

	/**
	 * The construction path for real: connect, query, read, close. sqlite3
	 * needs no server; the others skip without one.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testTheDriverCreatesNoDynamicProperty(string $driver): void {
		$command = escapeshellarg(PHP_BINARY).
			' -d display_errors=1 -d error_reporting=-1 '.
			escapeshellarg(FORUM_ROOT.'.dev/tests/UnitTests/Dblayer/dynamic_property_harness.php').' '.
			escapeshellarg($driver).' 2>&1';

		$output = (string)shell_exec($command);

		if (strpos($output, 'NO_SERVER') !== false)
			$this->markTestSkipped('no server for '.$driver);

		$this->assertStringContainsString('DONE', $output, $output);
		$this->assertStringContainsString('DYNAMIC=(none)', $output, $output);
		$this->assertStringNotContainsString('Deprecated:', $output, $output);
	}

	public function testTheLoaderCreatesNoDynamicProperty(): void {
		$loader = Loader::singleton();
		$loader->add_js('/js/probe.js');
		$loader->add_css('/style/probe.css');

		$this->assertSame(array(), $this->dynamicPropertiesOf($loader));
	}

	public function testTheFlashMessengerCreatesNoDynamicProperty(): void {
		global $forum_flash;

		$forum_flash->add_info('probe');
		$forum_flash->clear();

		$this->assertSame(array(), $this->dynamicPropertiesOf($forum_flash));
	}

	/** @return array<int, string> */
	private function dynamicPropertiesOf(object $object): array {
		$declared = array();
		foreach ((new ReflectionClass($object))->getProperties() as $property)
			$declared[] = $property->getName();

		// get_mangled_object_vars() reports private and protected properties
		// too; their keys carry a \0Class\0 prefix that has to come off first.
		$names = array();
		foreach (array_keys(get_mangled_object_vars($object)) as $key)
			$names[] = preg_replace('/^\0.*\0/', '', (string)$key);

		return array_values(array_diff($names, $declared));
	}
}
