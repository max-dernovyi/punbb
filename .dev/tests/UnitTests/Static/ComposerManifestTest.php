<?php
/**
 * The Composer manifest as a runtime contract: the extensions the forum calls
 * into must be declared in `require`, the vendor tree must stay out of the
 * repository while the lock file stays in it, and the PunBB\ namespace, the
 * installed modules' PunBBModule\ and, in development, the fixture modules'
 * namespace map onto trees of modules.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\ModuleTree;

class ComposerManifestTest extends TestCase {
	private const NAMESPACE_ROOT = 'include/PunBB/';

	private const FIXTURE_ROOT = '.dev/tests/fixtures/modules/';

	/** A template of a module, relative to either tree: lower case, so no namespace directory can share its name. */
	private const TEMPLATE = '#^(?:Module/)?[A-Z][A-Za-z0-9]*/templates/(?:[a-z0-9_-]+/)*[a-z0-9_-]+\.phtml$#';

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

	public function testTheLockFileIsPresentAndDescribesTheDependencies(): void {
		$lock = json_decode(file_get_contents(FORUM_ROOT.'composer.lock'), true);

		$this->assertIsArray($lock);
		$this->assertNotEmpty($lock['content-hash']);

		$required = array_values(array_filter(array_keys($this->manifest()['require']), static fn(string $p): bool => str_contains($p, '/')));
		$runtime = array_column($lock['packages'], 'name');
		sort($required);
		sort($runtime);
		$this->assertSame($required, $runtime, 'the forum pulls in exactly the runtime packages it requires, nothing transitive');

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

	/** A third-party module unpacked into modules/ autoloads through the PSR-4 map: nothing to dump or compile. */
	public function testThePunbbNamespacesAreMappedOntoTheirTrees(): void {
		$this->assertSame(array('psr-4' => array('PunBB\\' => self::NAMESPACE_ROOT, 'PunBBModule\\' => 'modules/')), $this->manifest()['autoload']);
	}

	/** The fixture modules autoload in development only; a --no-dev install never sees them. */
	public function testTheFixtureModulesAreMappedForDevelopmentOnly(): void {
		$this->assertSame(array('psr-4' => array('PunBBFixture\\Module\\' => self::FIXTURE_ROOT)), $this->manifest()['autoload-dev']);
	}

	/** @return array<string, array{string, string}> tree => root relative to FORUM_ROOT, the namespace it maps */
	public static function namespaceTreeProvider(): array {
		return array(
			'forum'		=> array(self::NAMESPACE_ROOT, 'PunBB\\'),
			'fixtures'	=> array(self::FIXTURE_ROOT, 'PunBBFixture\\Module\\'),
		);
	}

	/** @return list<string> every file below $tree, relative to it */
	private static function namespaceFiles(string $tree = self::NAMESPACE_ROOT): array {
		$root = FORUM_ROOT.$tree;
		$files = array();

		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file)
			$files[] = substr($file->getPathname(), strlen($root));

		sort($files);

		return $files;
	}

	/**
	 * The namespaces and the named class-likes $source declares.
	 *
	 * @return array{namespaces: list<string>, classes: list<string>}
	 */
	private static function declarations(string $source): array {
		$significant = array_values(array_filter(token_get_all($source), static fn($token): bool =>
			!is_array($token) || !in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)));

		$declared = array('namespaces' => array(), 'classes' => array());
		foreach ($significant as $position => $token)
		{
			$next = $significant[$position + 1] ?? null;
			if (!is_array($token) || !is_array($next))
				continue;

			if ($token[0] === T_NAMESPACE && in_array($next[0], array(T_STRING, T_NAME_QUALIFIED), true))
				$declared['namespaces'][] = $next[1];
			// Foo::class and new class are not declarations: neither is followed by a name.
			else if (in_array($token[0], array(T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM), true) && $next[0] === T_STRING)
				$declared['classes'][] = $next[1];
		}

		return $declared;
	}

	/** The scanner has to tell a declaration from a mention, or it guards nothing. */
	public function testTheDeclarationScannerCountsDeclarationsOnly(): void {
		$source = "<?php\nnamespace A\\B;\nfinal class C {\n\tpublic function f(): string {\n\t\t\$x = new class {};\n\t\treturn C::class;\n\t}\n}\ninterface D {}\n";

		$this->assertSame(array('namespaces' => array('A\\B'), 'classes' => array('C', 'D')), self::declarations($source));
		$this->assertSame(array('namespaces' => array(), 'classes' => array('E')), self::declarations("<?php\nenum E {}\n"));
	}

	#[DataProvider('namespaceTreeProvider')]
	public function testEveryFileInTheNamespaceTreeDeclaresOneClassNamedAfterItsPath(string $tree, string $namespace): void {
		$files = self::namespaceFiles($tree);
		$this->assertNotEmpty($files);

		$problems = array();
		foreach ($files as $file)
		{
			// A module's templates live beside its classes, in its templates/ directory
			if (preg_match(self::TEMPLATE, $file) === 1)
				continue;

			if (!str_ends_with($file, '.php'))
			{
				$problems[] = $file.': not a PHP class file';
				continue;
			}

			$declared = self::declarations((string) file_get_contents(FORUM_ROOT.$tree.$file));
			$expected = $namespace.str_replace('/', '\\', substr($file, 0, -4));

			if (count($declared['namespaces']) !== 1 || count($declared['classes']) !== 1)
				$problems[] = $file.': declares '.count($declared['namespaces']).' namespace(s) and '.count($declared['classes']).' class(es)';
			else if ($declared['namespaces'][0].'\\'.$declared['classes'][0] !== $expected)
				$problems[] = $file.': declares '.$declared['namespaces'][0].'\\'.$declared['classes'][0].', its path says '.$expected;
			else if (!class_exists($expected) && !interface_exists($expected) && !trait_exists($expected) && !enum_exists($expected))
				$problems[] = $file.': '.$expected.' does not autoload';
		}

		$this->assertSame(array(), $problems, implode("\n", $problems));
	}

	/** The fixture modules register on top of the forum's, as a third-party tree. */
	private static function registry(string $tree, string $namespace): ModuleRegistry {
		$forum = ModuleTree::core(FORUM_ROOT);

		return $tree === self::NAMESPACE_ROOT ? ModuleRegistry::discover($forum) : ModuleRegistry::discover($forum, new ModuleTree(FORUM_ROOT.$tree, $namespace));
	}

	#[DataProvider('namespaceTreeProvider')]
	public function testEveryClassBelongsToARegisteredModule(string $tree, string $namespace): void {
		$modules = self::registry($tree, $namespace)->names();
		$layout = $tree === self::NAMESPACE_ROOT ? '#^Module/([^/]+)/#' : '#^([^/]+)/#';

		$orphans = array();
		foreach (self::namespaceFiles($tree) as $file)
			if (preg_match($layout, $file, $match) !== 1 || !in_array($match[1], $modules, true))
				$orphans[] = $file;

		$this->assertSame(array(), $orphans, 'classes belonging to no registered module: '.implode(', ', $orphans));
	}

	#[DataProvider('namespaceTreeProvider')]
	public function testTheModuleGraphHasNoCycleAndWiresAContainer(string $tree, string $namespace): void {
		$registry = self::registry($tree, $namespace);
		$this->assertSame(array(), $registry->skipped());
		$position = array_flip($registry->names());

		foreach ($registry->modules() as $module)
			foreach (array_merge($module->dependencies(), $module->loadAfter()) as $predecessor)
				if (isset($position[$predecessor]))
					$this->assertLessThan($position[$module->name()], $position[$predecessor], $module->name().' loads before '.$predecessor);

		$this->assertInstanceOf(Container::class, $registry->container());
	}
}
