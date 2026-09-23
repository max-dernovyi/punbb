<?php
/**
 * The contract boundary: a module's Api\ namespace holds its service
 * interfaces and Api\Data\ the typed data interfaces they exchange. Nothing
 * crossing it is an array of columns, a mixed bag or an object with __call,
 * and every service contract is wired with an interceptor that only forwards
 * to its plugin chain.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\ModuleTree;
use PunBB\Module\Framework\Modules\Wiring;
use PunBBFixture\Module\Greeting\Api\GreeterInterface;

require_once __DIR__.'/BoundaryTypes.php';

class ServiceContractTest extends TestCase {
	private const MAX_PARAMETERS = 8;

	private const BROKEN = 'PunBBFixture\\BrokenContract\\';

	public static function setUpBeforeClass(): void {
		require_once FORUM_ROOT.'.dev/tests/fixtures/contracts/broken_contracts.php';
	}

	/** @return array<string, array{string, string}> */
	public static function treeProvider(): array {
		return array(
			'forum'		=> array(FORUM_ROOT.'include/PunBB/Module', 'PunBB\\Module\\'),
			'fixtures'	=> array(FORUM_ROOT.'.dev/tests/fixtures/modules', 'PunBBFixture\\Module\\'),
		);
	}

	private static function registry(string $directory, string $namespace): ModuleRegistry {
		$forum = ModuleTree::core(FORUM_ROOT);

		return $namespace === $forum->namespace ? ModuleRegistry::discover($forum) : ModuleRegistry::discover($forum, new ModuleTree($directory, $namespace));
	}

	/**
	 * Every class-like under a module's Api/ directory, by its path.
	 *
	 * @return list<string>
	 */
	private static function apiNames(string $directory, string $namespace, bool $servicesOnly = false): array {
		$names = array();
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file)
		{
			$path = substr($file->getPathname(), strlen($directory) + 1);
			if (preg_match($servicesOnly ? '#^[^/]+/Api/[^/]+\.php$#' : '#^[^/]+/Api/.+\.php$#', $path) === 1)
				$names[] = $namespace.str_replace('/', '\\', substr($path, 0, -4));
		}

		sort($names);

		return $names;
	}

	/** @return array<string, string> contract => interceptor, over every module */
	private static function contracts(ModuleRegistry $registry): array {
		$contracts = array();
		foreach ($registry->modules() as $module)
		{
			$wiring = new Wiring($module->name());
			$module->wire($wiring);
			$contracts += $wiring->contracts();
		}

		return $contracts;
	}

	/**
	 * What in an Api interface's signatures breaks the contract rules.
	 *
	 * @return list<string>
	 */
	private static function signatureProblems(string $interface): array {
		$class = new ReflectionClass($interface);
		$problems = array();

		if (!$class->isInterface())
			return array($interface.' is not an interface');

		if ($class->implementsInterface(ArrayAccess::class))
			$problems[] = $interface.' extends ArrayAccess';

		foreach ($class->getMethods() as $method)
		{
			$where = $class->getShortName().'::'.$method->getName().'()';

			if (str_starts_with($method->getName(), '__'))
			{
				$problems[] = $where.' is a magic method';
				continue;
			}

			if ($method->isStatic())
				$problems[] = $where.' is static';

			if ($method->getNumberOfParameters() > self::MAX_PARAMETERS)
				$problems[] = $where.' takes more than '.self::MAX_PARAMETERS.' parameters';

			foreach ($method->getParameters() as $parameter)
			{
				if ($parameter->isPassedByReference())
					$problems[] = $where.' takes $'.$parameter->getName().' by reference';

				array_push($problems, ...BoundaryTypes::parameterProblems($class, $method, $parameter));
			}

			array_push($problems, ...BoundaryTypes::returnProblems($class, $method));
		}

		return $problems;
	}

	/**
	 * What in an interceptor does more than forward each contract method to its chain.
	 *
	 * @return list<string>
	 */
	private static function interceptorProblems(string $contract, string $interceptor): array {
		$class = new ReflectionClass($interceptor);
		$lines = (array) file((string) $class->getFileName());
		$problems = array();

		foreach ($class->getMethods() as $method)
		{
			if ($method->isConstructor())
				continue;

			$name = $method->getName();
			if (!(new ReflectionClass($contract))->hasMethod($name))
			{
				$problems[] = $class->getShortName().' declares '.$name.'(), which is not on the contract';
				continue;
			}

			$source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
			$open = (int) strpos($source, '{');
			$body = (string) preg_replace('/\s+/', '', substr($source, $open + 1, (int) strrpos($source, '}') - $open - 1));

			$forward = '$this->plugins->call($this,__FUNCTION__,func_get_args(),$this->subject->'.$name.'(...));';
			if ($body !== ((string) $method->getReturnType() === 'void' ? $forward : 'return'.$forward))
				$problems[] = $class->getShortName().'::'.$name.'() does not only forward to its plugin chain';
		}

		return $problems;
	}

	#[DataProvider('treeProvider')]
	public function testEveryApiFileDeclaresAnInterface(string $directory, string $namespace): void {
		$problems = array();
		foreach (self::apiNames($directory, $namespace) as $name)
			if (!interface_exists($name))
				$problems[] = $name;

		$this->assertSame(array(), $problems, 'not interfaces: '.implode(', ', $problems));
	}

	#[DataProvider('treeProvider')]
	public function testEveryServiceContractIsWiredByItsModule(string $directory, string $namespace): void {
		$contracts = self::contracts(self::registry($directory, $namespace));

		$unwired = array_values(array_diff(self::apiNames($directory, $namespace, true), array_keys($contracts)));

		$this->assertSame(array(), $unwired, 'service contracts no module wires: '.implode(', ', $unwired));
	}

	#[DataProvider('treeProvider')]
	public function testEveryApiSignatureIsTyped(string $directory, string $namespace): void {
		$problems = array();
		foreach (self::apiNames($directory, $namespace) as $name)
			array_push($problems, ...self::signatureProblems($name));

		$this->assertSame(array(), $problems, implode("\n", $problems));
	}

	#[DataProvider('treeProvider')]
	public function testEveryInterceptorOnlyForwardsToItsChain(string $directory, string $namespace): void {
		$problems = array();
		foreach (self::contracts(self::registry($directory, $namespace)) as $contract => $interceptor)
			array_push($problems, ...self::interceptorProblems($contract, $interceptor));

		$this->assertSame(array(), $problems, implode("\n", $problems));
	}

	/** The fixtures are what gives the rules above something to check. */
	public function testTheFixtureTreeHasAContractToCheck(): void {
		$tree = self::treeProvider()['fixtures'];

		$this->assertContains(GreeterInterface::class, self::apiNames($tree[0], $tree[1], true));
		$this->assertContains('PunBBFixture\\Module\\Greeting\\Api\\Data\\GreetingInterface', self::apiNames($tree[0], $tree[1]));
	}

	public function testTheSignatureCheckAcceptsListsOfScalarsAndDataInterfaces(): void {
		$this->assertSame(array(), self::signatureProblems(self::BROKEN.'Api\\TypedInterface'));
	}

	/** @return array<string, array{string, list<string>}> */
	public static function brokenContractProvider(): array {
		return array(
			'__call'				=> array('MagicInterface', array('MagicInterface::__call() is a magic method')),
			'array of columns'		=> array('ColumnsInterface', array('ColumnsInterface::row() return is an array not documented as a list of scalars or data interfaces')),
			'list of column arrays'	=> array('RowsOfColumnsInterface', array('RowsOfColumnsInterface::rows() return is an array not documented as a list of scalars or data interfaces')),
			'undocumented array'	=> array('UndocumentedArrayInterface', array('UndocumentedArrayInterface::rows() return is an array not documented as a list of scalars or data interfaces')),
			'mixed'					=> array('MixedInterface', array('MixedInterface::find() $id is mixed, not a scalar or an Api\\Data interface')),
			'untyped'				=> array('UntypedInterface', array('UntypedInterface::find() $id is untyped', 'UntypedInterface::find() return is untyped')),
			'by reference'			=> array('ReferenceInterface', array('ReferenceInterface::fill() takes $title by reference')),
			'service parameter'		=> array('ServiceParameterInterface', array('ServiceParameterInterface::record() $journal is PunBBFixture\\Module\\Greeting\\Model\\Journal, not a scalar or an Api\\Data interface')),
			'static'				=> array('StaticInterface', array('StaticInterface::create() is static')),
			'too wide'				=> array('WideInterface', array('WideInterface::find() takes more than 8 parameters')),
		);
	}

	/** @param list<string> $expected */
	#[DataProvider('brokenContractProvider')]
	public function testTheSignatureCheckRejectsABrokenContract(string $interface, array $expected): void {
		$this->assertSame($expected, self::signatureProblems(self::BROKEN.'Api\\'.$interface));
	}

	public function testTheSignatureCheckRejectsAnArrayAccessContract(): void {
		$this->assertContains(self::BROKEN.'Api\\OffsetInterface extends ArrayAccess', self::signatureProblems(self::BROKEN.'Api\\OffsetInterface'));
	}

	public function testTheInterceptorCheckRejectsAMisroutedForward(): void {
		$this->assertSame(
			array('MisroutedInterceptor::greet() does not only forward to its plugin chain'),
			self::interceptorProblems(GreeterInterface::class, self::BROKEN.'Interceptor\\MisroutedInterceptor')
		);
	}

	public function testTheInterceptorCheckRejectsWorkBesideTheForward(): void {
		$this->assertSame(
			array('ChattyInterceptor::greet() does not only forward to its plugin chain', 'ChattyInterceptor declares shout(), which is not on the contract'),
			self::interceptorProblems(GreeterInterface::class, self::BROKEN.'Interceptor\\ChattyInterceptor')
		);
	}
}
