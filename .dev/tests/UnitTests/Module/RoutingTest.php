<?php
/**
 * The front controller's parts, constructed directly: the request read from
 * the server's globals, the route map modules declare, the rewrite rules of a
 * SEF scheme, and a module's controller answering through the front controller
 * with a response instead of an exit.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Module as DatabaseModule;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Module as FrameworkModule;
use PunBB\Module\Framework\Modules\ModuleException;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Framework\Routing\FrontController;
use PunBB\Module\Framework\Routing\RewriteRules;
use PunBB\Module\Framework\Routing\Route;
use PunBBFixture\Module\Courtesy\Module as CourtesyModule;
use PunBBFixture\Module\Greeting\Controller\GreetingController;
use PunBBFixture\Module\Greeting\Model\Journal;
use PunBBFixture\Module\Greeting\Module as GreetingModule;

class RoutingTest extends TestCase {
	/** @param Closure(Wiring): void $wire */
	private static function module(string $name, Closure $wire): ModuleInterface {
		return new class($name, $wire) implements ModuleInterface {
			public function __construct(private string $name, private Closure $wire) {}

			public function name(): string {
				return $this->name;
			}

			public function dependencies(): array {
				return array();
			}

			public function loadAfter(): array {
				return array();
			}

			public function version(): string {
				return '1.0.0';
			}

			public function wire(Wiring $wiring): void {
				($this->wire)($wiring);
			}
		};
	}

	/** @return array<string, array{string, string, string, string}> */
	public static function requestProvider(): array {
		return array(
			'the root'				=> array('/index.php', '/', '/', ''),
			'a script'				=> array('/index.php', '/viewtopic.php?id=1&p=2', '/', 'viewtopic.php'),
			'a directory'			=> array('/index.php', '/admin/', '/', 'admin/'),
			'a pretty URL'			=> array('/index.php', '/topic/1/test-post/', '/', 'topic/1/test-post/'),
			'decoded'				=> array('/index.php', '/search/k%C3%BCber/', '/', 'search/küber/'),
			'in a subdirectory'		=> array('/forum/index.php', '/forum/admin/users.php?p=2', '/forum/', 'admin/users.php'),
			'the subdirectory root'	=> array('/forum/index.php', '/forum/', '/forum/', ''),
		);
	}

	#[DataProvider('requestProvider')]
	public function testTheRequestPathIsWhatFollowsTheFrontControllersDirectory(string $script, string $uri, string $base, string $path): void {
		$request = Request::fromGlobals(array('SCRIPT_NAME' => $script, 'REQUEST_URI' => $uri, 'REQUEST_METHOD' => 'post'), array('id' => '1'), array('a' => 'b'), array('c' => 'd'));

		$this->assertSame($base, $request->base);
		$this->assertSame($path, $request->path);
		$this->assertSame('POST', $request->method);
		$this->assertSame(array('id' => '1'), $request->query);
		$this->assertSame(array('a' => 'b'), $request->post);
		$this->assertSame(array('c' => 'd'), $request->cookies);
	}

	/** A script asking, as legacy code told by FORUM_REQUEST_AJAX, is answered in JSON; the rewrite keeps that. */
	public function testARequestKnowsWhetherAScriptSentIt(): void {
		$server = array('SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/topic/1/');

		$this->assertFalse(Request::fromGlobals($server, array(), array(), array())->xhr);
		$this->assertFalse(Request::fromGlobals($server + array('HTTP_X_REQUESTED_WITH' => 'fetch'), array(), array(), array())->xhr);

		$request = Request::fromGlobals($server + array('HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'), array(), array(), array());
		$this->assertTrue($request->xhr);
		$this->assertTrue($request->rewritten('viewtopic.php', array('id' => '1'))->xhr);
	}

	/** A feed reader signs in with HTTP Basic authentication; the rewrite keeps the credentials. */
	public function testARequestCarriesTheCredentialsOfBasicAuthentication(): void {
		$server = array('SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/extern.php');

		$anonymous = Request::fromGlobals($server + array('PHP_AUTH_PW' => 'without a user'), array(), array(), array());
		$this->assertSame(array(null, null), array($anonymous->authUser, $anonymous->authPassword));

		$request = Request::fromGlobals($server + array('PHP_AUTH_USER' => 'reader', 'PHP_AUTH_PW' => 'secret'), array(), array(), array());
		$this->assertSame(array('reader', 'secret'), array($request->authUser, $request->authPassword));

		$rewritten = $request->rewritten('extern.php', array('action' => 'feed'));
		$this->assertSame(array('reader', 'secret'), array($rewritten->authUser, $rewritten->authPassword));

		$this->assertSame(array('reader', null), array(Request::fromGlobals($server + array('PHP_AUTH_USER' => 'reader'), array(), array(), array())->authUser,
			Request::fromGlobals($server + array('PHP_AUTH_USER' => 'reader'), array(), array(), array())->authPassword));
	}

	public function testARewrittenRequestTakesTheTargetAndLaysTheParametersOverTheQuery(): void {
		$request = new Request('GET', '/', 'topic/1/', array('id' => '9', 'sort' => 'asc'));
		$rewritten = $request->rewritten('viewtopic.php', array('id' => '1', 'p' => '2'));

		$this->assertSame('viewtopic.php', $rewritten->path);
		$this->assertSame(array('id' => '1', 'sort' => 'asc', 'p' => '2'), $rewritten->query);
		$this->assertSame('topic/1/', $request->path);
	}

	/**
	 * The request is read before include/common.php strips the characters that
	 * mess with a page from the superglobals; the front controller hands the
	 * controller the parameters as the bootstrap left them.
	 */
	public function testAControllerReadsTheParametersTheBootstrapCleaned(): void {
		$request = new Request('POST', '/forum/', 'register.php', array('a' => "x\u{200E}"), array('req_username' => "ad\u{202E}min"), array(), true, 'reader', 'secret');
		$cleaned = $request->withParameters(array('a' => 'x'), array('req_username' => 'admin'), array('c' => '1'));

		$this->assertSame(array('POST', '/forum/', 'register.php', true, 'reader', 'secret'), array($cleaned->method, $cleaned->base, $cleaned->path, $cleaned->xhr, $cleaned->authUser, $cleaned->authPassword));
		$this->assertSame(array(array('a' => 'x'), array('req_username' => 'admin'), array('c' => '1')), array($cleaned->query, $cleaned->post, $cleaned->cookies));
		$this->assertStringContainsString("\trequire FORUM_ROOT.'include/common.php';\n\n\t// The controller reads the parameters include/common.php cleaned, as a page script read the superglobals\n\t\$forum_request = \$forum_request->withParameters(\$_GET, \$_POST, \$_COOKIE);\n\n\tforum_send_response(",
			(string) file_get_contents(FORUM_ROOT.'index.php'));
	}

	public function testTheRouteMapMatchesEveryPathExactlyAndNothingElse(): void {
		$router = (new ModuleRegistry(
			self::module('Pages', function (Wiring $wiring): void {
				$wiring->route(array('index.php', ''), GreetingController::class, fn (): object => new stdClass());
				$wiring->route(array('admin/index.php', 'admin/'), GreetingController::class, fn (): object => new stdClass());
			})
		))->router();

		$this->assertSame('index.php', self::route($router->match(''))->paths[0]);
		$this->assertSame('index.php', self::route($router->match('index.php'))->paths[0]);
		$this->assertSame('admin/index.php', self::route($router->match('admin/'))->paths[0]);

		foreach (array('Index.php', 'index.php/', '/index.php', 'admin', 'admin/index.php/x', 'rewrite.php') as $path)
			$this->assertNull($router->match($path), $path);
	}

	public function testRoutesKeepModuleOrderThenDeclarationOrder(): void {
		$routes = (new ModuleRegistry(
			self::module('First', function (Wiring $wiring): void {
				$wiring->route(array('b.php'), GreetingController::class, fn (): object => new stdClass());
				$wiring->route(array('a.php'), GreetingController::class, fn (): object => new stdClass());
			}),
			self::module('Second', function (Wiring $wiring): void {
				$wiring->route(array('c.php'), GreetingController::class, fn (): object => new stdClass());
			})
		))->router()->routes();

		$this->assertSame(array(array('First', 'b.php'), array('First', 'a.php'), array('Second', 'c.php')),
			array_map(static fn (Route $route): array => array($route->module, $route->paths[0]), $routes));
	}

	public function testTwoModulesRoutingOnePathFailNamingBoth(): void {
		$registry = new ModuleRegistry(
			self::module('First', fn (Wiring $wiring) => $wiring->route(array('index.php'), GreetingController::class, fn (): object => new stdClass())),
			self::module('Second', fn (Wiring $wiring) => $wiring->route(array('', 'index.php'), GreetingController::class, fn (): object => new stdClass()))
		);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Modules First and Second both route "index.php"');

		$registry->router();
	}

	/** @return array<string, array{Closure(Wiring): void, string}> */
	public static function badRouteProvider(): array {
		return array(
			'no path'					=> array(fn (Wiring $w) => $w->route(array(), GreetingController::class, fn (): object => new stdClass()), 'declares a route without a path'),
			'an absolute path'			=> array(fn (Wiring $w) => $w->route(array('/index.php'), GreetingController::class, fn (): object => new stdClass()), 'routes "/index.php"'),
			'a traversal'				=> array(fn (Wiring $w) => $w->route(array('../index.php'), GreetingController::class, fn (): object => new stdClass()), 'routes "../index.php"'),
			'an empty segment'			=> array(fn (Wiring $w) => $w->route(array('admin//index.php'), GreetingController::class, fn (): object => new stdClass()), 'routes "admin//index.php"'),
			'not a controller'			=> array(fn (Wiring $w) => $w->route(array('index.php'), Journal::class, fn (): object => new Journal()), 'routes to '.Journal::class.', which does not implement'),
		);
	}

	#[DataProvider('badRouteProvider')]
	public function testARouteThatIsNotAPathBelowTheRootOrNotAControllerFailsWhenWired(Closure $wire, string $message): void {
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage($message);

		$wire(new Wiring('Pages'));
	}

	/** A request to a quiet route leaves no visit behind: the front controller tells the bootstrap before it boots. */
	public function testARouteIsQuietOnlyWhenItsModuleSaysSo(): void {
		$router = (new ModuleRegistry(
			self::module('Pages', function (Wiring $wiring): void {
				$wiring->route(array('greeting.php'), GreetingController::class, fn (): object => new stdClass());
				$wiring->route(array('feed.php'), GreetingController::class, fn (): object => new stdClass(), quiet: true);
				$wiring->route(array('act.php'), GreetingController::class, fn (): object => new stdClass(), quietWith: array('action'));
			})
		))->router();

		$plain = new Request('GET', '/', 'x.php');
		$acting = new Request('GET', '/', 'x.php', array('action' => 'rules'));

		$this->assertSame(array(false, false), array(self::route($router->match('greeting.php'))->isQuietFor($plain), self::route($router->match('greeting.php'))->isQuietFor($acting)));
		$this->assertSame(array(true, true), array(self::route($router->match('feed.php'))->isQuietFor($plain), self::route($router->match('feed.php'))->isQuietFor($acting)));
		$this->assertSame(array(false, true), array(self::route($router->match('act.php'))->isQuietFor($plain), self::route($router->match('act.php'))->isQuietFor($acting)));
		$this->assertStringContainsString("if (\$forum_route->isQuietFor(\$forum_request) && !defined('FORUM_QUIET_VISIT'))\n\t\tdefine('FORUM_QUIET_VISIT', 1);", (string) file_get_contents(FORUM_ROOT.'index.php'));
	}

	/** A controller that checks the token of a POST itself takes it out of the gate, which the front controller tells the bootstrap before it boots. */
	public function testARouteChecksItsOwnTokenOnlyWhenItsModuleSaysSo(): void {
		$router = (new ModuleRegistry(
			self::module('Pages', function (Wiring $wiring): void {
				$wiring->route(array('greeting.php'), GreetingController::class, fn (): object => new stdClass());
				$wiring->route(array('post.php'), GreetingController::class, fn (): object => new stdClass(), checksOwnToken: true);
			})
		))->router();

		$this->assertFalse(self::route($router->match('greeting.php'))->checksOwnToken);
		$this->assertTrue(self::route($router->match('post.php'))->checksOwnToken);
		$this->assertStringContainsString("if (\$forum_route->checksOwnToken && !defined('FORUM_SKIP_CSRF_CONFIRM'))\n\t\tdefine('FORUM_SKIP_CSRF_CONFIRM', 1);\n\n\trequire FORUM_ROOT.'include/common.php';", (string) file_get_contents(FORUM_ROOT.'index.php'));
	}

	/** The installer and the updater run before a usable configuration exists: the front controller boots no board for them. */
	public function testASetupRouteRunsWithoutTheBoard(): void {
		$router = (new ModuleRegistry(
			self::module('Pages', function (Wiring $wiring): void {
				$wiring->route(array('greeting.php'), GreetingController::class, fn (): object => new stdClass());
				$wiring->route(array('admin/install.php'), GreetingController::class, fn (): object => new stdClass(), setup: true);
			})
		))->router();

		$this->assertFalse(self::route($router->match('greeting.php'))->setup);
		$this->assertTrue(self::route($router->match('admin/install.php'))->setup);

		$index = (string) file_get_contents(FORUM_ROOT.'index.php');
		$this->assertStringContainsString("if (\$forum_route->setup)\n{\n\trequire FORUM_ROOT.'include/setup.php';", $index);
		$this->assertLessThan(strpos($index, "require FORUM_ROOT.'include/common.php';"), strpos($index, "require FORUM_ROOT.'include/setup.php';"));
	}

	private static function route(?Route $route): Route {
		self::assertInstanceOf(Route::class, $route);

		return $route;
	}

	/** Routing happens before the forum boots, so it builds nothing. */
	public function testTheRouteMapIsBuiltWithoutAContainer(): void {
		$router = (new ModuleRegistry(
			self::module('Pages', function (Wiring $wiring): void {
				$wiring->service('never', fn (): object => throw new LogicException('built a service'));
				$wiring->route(array('greeting.php'), GreetingController::class, fn (): object => throw new LogicException('built a controller'));
			})
		))->router();

		$this->assertInstanceOf(Route::class, $router->match('greeting.php'));
	}

	/** Greeting routes to its controller; the response carries what both modules' plugins made of the greeting. */
	public function testAModulesControllerAnswersItsRouteWithAResponse(): void {
		$registry = new ModuleRegistry(new FrameworkModule(), new DatabaseModule(), new CourtesyModule(), new GreetingModule());
		$route = $registry->router()->match('greeting/');
		$this->assertInstanceOf(Route::class, $route);

		$container = $registry->container();
		$request = Request::fromGlobals(array('SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/greeting/?name=+rick+'), array('name' => ' rick '), array(), array());
		$response = $container->get(FrontController::class)->handle($route, $request);

		$this->assertSame('Hello, Dr. Rick. Welcome back!', $response->body);
		$this->assertSame(200, $response->status);
		$this->assertSame(array('Content-Type' => 'text/plain; charset=utf-8'), $response->headers);
		$this->assertContains('Greeter::greet(Dr. Rick)', $container->get(Journal::class)->entries());
	}

	public function testAControllerFactoryBuildingSomethingElseFailsNamingTheModule(): void {
		$registry = new ModuleRegistry(new FrameworkModule(),
			self::module('Pages', fn (Wiring $wiring) => $wiring->route(array('greeting.php'), GreetingController::class, fn (): object => new Journal())));

		$route = $registry->router()->match('greeting.php');
		$this->assertInstanceOf(Route::class, $route);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Module Pages wired controller '.GreetingController::class.' to a '.Journal::class);

		$registry->container()->get(FrontController::class)->handle($route, new Request('GET', '/', 'greeting.php'));
	}

	public function testAResponseIsSentOnlyWhenTheFrontControllerSendsIt(): void {
		$response = new Response('<p>body</p>', 404);

		$this->expectOutputString('<p>body</p>');
		$response->send();
	}

	/** @return array<string, array{array<mixed>, string, ?array{string, array<array-key, string>}}> */
	public static function rewriteProvider(): array {
		$default = array(
			'/^topic[\/_-]?([0-9]+).*(new|last)[\/_-]?(posts?)(\.html?|\/)?$/i'	=> 'viewtopic.php?id=$1&action=$2',
			'/^(forum|topic)[\/_-]?([0-9]+).*(\.html?|\/)?$/i'					=> 'view$1.php?id=$2',
			'/^(login|search|register)(\.html?|\/)?$/i'							=> '$1.php',
		);

		return array(
			'the first matching rule'	=> array($default, 'topic1/new/posts/', array('viewtopic.php', array('id' => '1', 'action' => 'new'))),
			'a later rule'				=> array($default, 'forum/2/test-forum/', array('viewforum.php', array('id' => '2'))),
			'no query'					=> array($default, 'login.html', array('login.php', array())),
			'no rule'					=> array($default, 'no-such-page.html', null),
			'a decoded value'			=> array(array('/^k(.*)$/' => 'search.php?keywords=$1'), 'ka%2Bb', array('search.php', array('keywords' => 'a+b'))),
			'split on = and &'			=> array(array('/^k(.*)$/' => 'search.php?keywords=$1&x'), 'ka=b', array('search.php', array('keywords' => 'a', 'x' => ''))),
			'an empty parameter'		=> array(array('/^f$/' => 'extern.php?fid=1&&type=rss'), 'f', array('extern.php', array('fid' => '1', '' => '', 'type' => 'rss'))),
			'not a rule'				=> array(array(0 => 'index.php', '/^x$/' => array('index.php'), '/^x$/i' => 'misc.php'), 'x', array('misc.php', array())),
		);
	}

	/**
	 * @param array<mixed> $rules
	 * @param ?array{string, array<array-key, string>} $expected
	 */
	#[DataProvider('rewriteProvider')]
	public function testARewriteRuleTurnsAPrettyPathIntoARoutedOne(array $rules, string $path, ?array $expected): void {
		$rewrite = (new RewriteRules($rules))->rewrite($path);

		$this->assertSame($expected, $rewrite === null ? null : array($rewrite->target, $rewrite->parameters));
	}
}
