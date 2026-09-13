<?php
/**
 * A plugin runs before or after a contract method and nothing else: a before
 * may rewrite the arguments, an after the result, neither can skip the call,
 * and a declaration that does not fit the contract fails the container
 * assembly instead of a request.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleException;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Framework\Plugin\PluginDeclaration;
use PunBB\Module\Framework\Plugin\PluginException;
use PunBB\Module\Framework\Plugin\PluginManager;
use PunBBFixture\Module\Greeting\Api\Data\GreetingInterface;
use PunBBFixture\Module\Greeting\Api\GreeterInterface;
use PunBBFixture\Module\Greeting\Interceptor\GreeterInterceptor;
use PunBBFixture\Module\Greeting\Model\Greeter;
use PunBBFixture\Module\Greeting\Model\Journal;

class PluginManagerTest extends TestCase {
	/** @param list<object> $plugins */
	private static function chain(array $plugins): PluginChain {
		return new PluginChain($plugins);
	}

	/** @param list<object|string> $plugins plugin instances, or class names built with no arguments */
	private static function manager(array $plugins): PluginManager {
		$declarations = array();
		foreach ($plugins as $plugin)
		{
			$class = is_object($plugin) ? $plugin::class : $plugin;
			$declarations[] = new PluginDeclaration('Probe', GreeterInterface::class, $class, fn (): object => is_object($plugin) ? $plugin : new $class());
		}

		return new PluginManager(array(GreeterInterface::class => GreeterInterceptor::class), $declarations);
	}

	private function assertManagerFails(string $message, object|string ...$plugins): void {
		try {
			self::manager($plugins);
			$this->fail('the plugin manager accepted: '.$message);
		}
		catch (ModuleException $e) {
			$this->assertSame($message, $e->getMessage());
		}
	}

	public function testBeforesRunInOrderAndTheLastRewriteReachesTheSubject(): void {
		$log = new ArrayObject();
		$first = new class($log) {
			public function __construct(private ArrayObject $log) {}

			public function beforeGreet(object $subject, string $name): ?array {
				$this->log[] = 'first('.$name.')';
				return array(trim($name));
			}
		};
		$second = new class($log) {
			public function __construct(private ArrayObject $log) {}

			public function beforeGreet(object $subject, string $name): ?array {
				$this->log[] = 'second('.$name.')';
				return array(strtoupper($name));
			}
		};

		$result = self::chain(array($first, $second))->call(new stdClass(), 'greet', array(' rick '), function (string $name) use ($log): string {
			$log[] = 'subject('.$name.')';
			return 'Hello, '.$name;
		});

		$this->assertSame(array('first( rick )', 'second(rick)', 'subject(RICK)'), $log->getArrayCopy());
		$this->assertSame('Hello, RICK', $result);
	}

	public function testAftersRunInOrderWithTheRewrittenArgumentsAndTheLastRewriteReachesTheCaller(): void {
		$rewrite = new class {
			public function beforeGreet(object $subject, string $name): ?array {
				return array('Dr. '.$name);
			}
		};
		$first = new class {
			public function afterGreet(object $subject, string $result, string $name): string {
				return $result.' ['.$name.']';
			}
		};
		$second = new class {
			public function afterGreet(object $subject, string $result): string {
				return $result.'!';
			}
		};

		$result = self::chain(array($first, $rewrite, $second))->call(new stdClass(), 'greet', array('Rick'), fn (string $name): string => 'Hello, '.$name);

		$this->assertSame('Hello, Dr. Rick [Dr. Rick]!', $result);
	}

	public function testABeforeReturningNullKeepsTheArguments(): void {
		$observer = new class {
			public function beforeGreet(object $subject, string $name): ?array {
				return null;
			}
		};

		$this->assertSame('Hello, Rick', self::chain(array($observer))->call(new stdClass(), 'greet', array('Rick'), fn (string $name): string => 'Hello, '.$name));
	}

	/** @return array<string, array{mixed}> */
	public static function answerProvider(): array {
		return array(
			'a result instead of arguments'	=> array('Hello from the plugin'),
			'named arguments'				=> array(array('name' => 'Rick')),
		);
	}

	#[DataProvider('answerProvider')]
	public function testABeforeCannotAnswerInsteadOfTheSubject(mixed $answer): void {
		$shortCircuit = new class($answer) {
			public function __construct(private mixed $answer) {}

			public function beforeGreet(object $subject, string $name): mixed {
				return $this->answer;
			}
		};
		$called = false;

		try {
			self::chain(array($shortCircuit))->call(new stdClass(), 'greet', array('Rick'), function (string $name) use (&$called): string {
				$called = true;
				return $name;
			});
			$this->fail('a before answered for the subject');
		}
		catch (PluginException $e) {
			$this->assertStringEndsWith('::beforeGreet returned neither null nor a list of arguments', $e->getMessage());
		}

		$this->assertFalse($called);
	}

	public function testEveryPluginIsHandedTheContractItsCallerHolds(): void {
		$plugin = new class {
			public array $subjects = array();

			public function beforeGreet(object $subject, string $name): ?array {
				$this->subjects[] = $subject;
				return null;
			}

			public function afterGreet(object $subject, string $result): string {
				$this->subjects[] = $subject;
				return $result;
			}
		};
		$contract = new stdClass();

		self::chain(array($plugin))->call($contract, 'greet', array('Rick'), fn (string $name): string => $name);

		$this->assertSame(array($contract, $contract), $plugin->subjects);
	}

	public function testAMethodNoPluginNamesRunsStraightThrough(): void {
		$plugin = new class {
			public function beforeGreet(object $subject, string $name): ?array {
				throw new LogicException('greet was not called');
			}
		};

		$this->assertSame('Goodbye, Rick', self::chain(array($plugin))->call(new stdClass(), 'farewell', array('Rick'), fn (string $name): string => 'Goodbye, '.$name));
	}

	public function testTheAfterOfAVoidMethodSeesNull(): void {
		$plugin = new class {
			public array $results = array();

			public function afterForget(object $subject, null $result): null {
				$this->results[] = $result;
				return null;
			}
		};

		self::chain(array($plugin))->call(new stdClass(), 'forget', array(), function (): void {});

		$this->assertSame(array(null), $plugin->results);
	}

	public function testAnUnpluggedContractResolvesToItsSubject(): void {
		$subject = new Greeter(new Journal());

		$this->assertSame($subject, self::manager(array())->intercept(GreeterInterface::class, $subject, new Container(array())));
	}

	public function testAPluggedContractResolvesToItsInterceptor(): void {
		$plugin = new class {
			public function afterGreet(GreeterInterface $subject, GreetingInterface $result): GreetingInterface {
				return $result->withText('intercepted');
			}
		};

		$contract = self::manager(array($plugin))->intercept(GreeterInterface::class, new Greeter(new Journal()), new Container(array()));

		$this->assertInstanceOf(GreeterInterceptor::class, $contract);
		$this->assertSame('intercepted', $contract->greet('Rick')->text());
	}

	public function testAPluginOnAnUnwiredContractFails(): void {
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module Probe plugs '.Countable::class.', which no module wires as a contract');
		new PluginManager(array(), array(new PluginDeclaration('Probe', Countable::class, stdClass::class, fn (): object => new stdClass())));
	}

	public function testAPluginThatIsNotAClassFails(): void {
		$this->assertManagerFails('Module Probe plugs '.GreeterInterface::class.' with Nowhere\\Plugin, which is not a class', 'Nowhere\\Plugin');
	}

	public function testAnAroundPluginFails(): void {
		$around = new class {
			public function aroundGreet(GreeterInterface $subject, Closure $proceed, string $name): GreetingInterface {
				return $proceed($name);
			}
		};

		$this->assertManagerFails('Module Probe declares '.$around::class.'::aroundGreet; a plugin is before and after only', $around);
	}

	public function testAModuleDeclaringAnAroundPluginFailsTheContainerAssembly(): void {
		$around = new class {
			public function beforeGreet(GreeterInterface $subject, string $name): ?array {
				return null;
			}

			public function aroundFarewell(GreeterInterface $subject, Closure $proceed, string $name): GreetingInterface {
				return $proceed($name);
			}
		};
		$module = new class($around::class) implements ModuleInterface {
			public function __construct(private string $plugin) {}

			public function name(): string {
				return 'Greeting';
			}

			public function dependencies(): array {
				return array();
			}

			public function loadAfter(): array {
				return array();
			}

			public function wire(Wiring $wiring): void {
				$wiring->contract(GreeterInterface::class, GreeterInterceptor::class, fn (Container $c): object => new Greeter(new Journal()));
				$wiring->plugin(GreeterInterface::class, $this->plugin, fn (): object => new ($this->plugin)());
			}
		};

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('::aroundFarewell; a plugin is before and after only');
		(new ModuleRegistry($module))->container();
	}

	/** @return array<string, array{object}> */
	public static function strayMethodProvider(): array {
		return array(
			'no such method'		=> array(new class {
				public function beforeGreat(GreeterInterface $subject, string $name): ?array {
					return null;
				}
			}),
			'wrong case'			=> array(new class {
				public function beforegreet(GreeterInterface $subject, string $name): ?array {
					return null;
				}
			}),
			'upper case'			=> array(new class {
				public function beforeGREET(GreeterInterface $subject, string $name): ?array {
					return null;
				}
			}),
			'neither before nor after'	=> array(new class {
				public function greet(GreeterInterface $subject, string $name): ?array {
					return null;
				}
			}),
			'static'				=> array(new class {
				public static function beforeGreet(GreeterInterface $subject, string $name): ?array {
					return null;
				}
			}),
		);
	}

	#[DataProvider('strayMethodProvider')]
	public function testAPublicMethodPluggingNoContractMethodFails(object $plugin): void {
		$method = (new ReflectionClass($plugin))->getMethods(ReflectionMethod::IS_PUBLIC)[0]->getName();

		$this->assertManagerFails($plugin::class.'::'.$method.' plugs no method of '.GreeterInterface::class, $plugin);
	}

	/** @return array<string, array{object, string}> */
	public static function signatureProvider(): array {
		$contract = GreeterInterface::class;
		$greeting = GreetingInterface::class;

		return array(
			'before without the subject'	=> array(new class {
				public function beforeGreet(string $name): ?array {
					return null;
				}
			}, '::beforeGreet must take ('.$contract.', string)'),
			'before with a wrong argument'	=> array(new class {
				public function beforeGreet(GreeterInterface $subject, int $name): ?array {
					return null;
				}
			}, '::beforeGreet must take ('.$contract.', string)'),
			'before with an extra argument'	=> array(new class {
				public function beforeGreet(GreeterInterface $subject, string $name, string $title): ?array {
					return null;
				}
			}, '::beforeGreet must take ('.$contract.', string)'),
			'before returning a result'		=> array(new class {
				public function beforeGreet(GreeterInterface $subject, string $name): GreetingInterface {
					throw new LogicException();
				}
			}, '::beforeGreet must return ?array'),
			'after without the result'		=> array(new class {
				public function afterGreet(GreeterInterface $subject): GreetingInterface {
					throw new LogicException();
				}
			}, '::afterGreet must take ('.$contract.', '.$greeting.', string)'),
			'after changing the type'		=> array(new class {
				public function afterGreet(GreeterInterface $subject, GreetingInterface $result): string {
					return '';
				}
			}, '::afterGreet must return '.$greeting),
		);
	}

	#[DataProvider('signatureProvider')]
	public function testAPluginMethodMustFollowTheContractSignature(object $plugin, string $message): void {
		$this->assertManagerFails($plugin::class.$message, $plugin);
	}

	public function testAPluginMustKeepTheDefaultTheContractGivesAnArgument(): void {
		require_once FORUM_ROOT.'.dev/tests/fixtures/contracts/broken_contracts.php';
		$contract = PunBBFixture\BrokenContract\Api\TypedInterface::class;
		$required = new class {
			public function beforeIds(PunBBFixture\BrokenContract\Api\TypedInterface $subject, bool $visible): ?array {
				return null;
			}
		};
		$optional = new class {
			public function afterIds(PunBBFixture\BrokenContract\Api\TypedInterface $subject, ?array $result, bool $visible = true): ?array {
				return $result;
			}
		};
		$different = new class {
			public function beforeIds(PunBBFixture\BrokenContract\Api\TypedInterface $subject, bool $visible = false): ?array {
				return null;
			}
		};
		$declare = fn (object $plugin): PluginDeclaration => new PluginDeclaration('Probe', $contract, $plugin::class, fn (): object => $plugin);

		new PluginManager(array($contract => GreeterInterceptor::class), array($declare($optional)));

		foreach (array($required, $different) as $plugin)
		{
			try {
				new PluginManager(array($contract => GreeterInterceptor::class), array($declare($plugin)));
				$this->fail($plugin::class.' was accepted');
			}
			catch (ModuleException $e) {
				$this->assertSame($plugin::class.'::beforeIds must give $visible the default '.$contract.'::ids gives it', $e->getMessage());
			}
		}
	}

	public function testAPluginPluggingNothingFails(): void {
		$idle = new class {};

		$this->assertManagerFails('Module Probe plugs '.GreeterInterface::class.' with '.$idle::class.', which declares no before or after method', $idle);
	}

	public function testAFactoryBuildingAnotherClassFails(): void {
		$plugin = new class {
			public function beforeGreet(GreeterInterface $subject, string $name): ?array {
				return null;
			}
		};
		$manager = new PluginManager(
			array(GreeterInterface::class => GreeterInterceptor::class),
			array(new PluginDeclaration('Probe', GreeterInterface::class, $plugin::class, fn (): object => new stdClass()))
		);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module Probe wired plugin '.$plugin::class.' to a stdClass');
		$manager->intercept(GreeterInterface::class, new Greeter(new Journal()), new Container(array()));
	}
}
