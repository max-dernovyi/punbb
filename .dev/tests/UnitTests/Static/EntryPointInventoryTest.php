<?php
/**
 * The route map is pinned, and so is the way each routed page is gated.
 *
 * The web server runs one script, the front controller, and every address the
 * forum answers is a path in the route map. A path appearing in the map
 * without being added here has not been audited, so it fails the build; the
 * same goes for a second page skipping the global CSRF gate or running before
 * the board is booted, and for a PHP file the server could run by itself.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\ModuleTree;
use PunBB\Module\Framework\Routing\Router;

class EntryPointInventoryTest extends TestCase {
	/**
	 * The front controller, and the two deprecated includes of a page still
	 * built on $tpl_main; both exit unless the forum is loaded. config.php is
	 * the installer's, absent from a bare checkout.
	 *
	 * @var list<string>
	 */
	private const ROOT_SCRIPTS = array('footer.php', 'header.php', 'index.php');

	/**
	 * path => the controller serving it.
	 *
	 * @var array<string, string>
	 */
	private const ROUTES = array(
		''						=> 'PunBB\\Module\\Index\\Controller\\IndexController',
		'admin'					=> 'PunBB\\Module\\AdminIndex\\Controller\\InformationController',
		'admin/'				=> 'PunBB\\Module\\AdminIndex\\Controller\\InformationController',
		'admin/bans.php'		=> 'PunBB\\Module\\Bans\\Controller\\BansController',
		'admin/categories.php'	=> 'PunBB\\Module\\Categories\\Controller\\CategoriesController',
		'admin/censoring.php'	=> 'PunBB\\Module\\Censoring\\Controller\\CensoringController',
		'admin/db_update.php'	=> 'PunBB\\Module\\Update\\Controller\\UpdateController',
		'admin/extensions.php'	=> 'PunBB\\Module\\Extensions\\Controller\\ExtensionsController',
		'admin/forums.php'		=> 'PunBB\\Module\\Forums\\Controller\\ForumsController',
		'admin/groups.php'		=> 'PunBB\\Module\\Groups\\Controller\\GroupsController',
		'admin/index.php'		=> 'PunBB\\Module\\AdminIndex\\Controller\\InformationController',
		'admin/install.php'		=> 'PunBB\\Module\\Install\\Controller\\InstallController',
		'admin/prune.php'		=> 'PunBB\\Module\\Prune\\Controller\\PruneController',
		'admin/ranks.php'		=> 'PunBB\\Module\\Ranks\\Controller\\RanksController',
		'admin/reindex.php'		=> 'PunBB\\Module\\Reindex\\Controller\\ReindexController',
		'admin/reports.php'		=> 'PunBB\\Module\\Reports\\Controller\\ReportsController',
		'admin/settings.php'	=> 'PunBB\\Module\\Settings\\Controller\\SettingsController',
		'admin/users.php'		=> 'PunBB\\Module\\Users\\Controller\\UsersController',
		'delete.php'			=> 'PunBB\\Module\\Delete\\Controller\\DeleteController',
		'edit.php'				=> 'PunBB\\Module\\Edit\\Controller\\EditController',
		'extern.php'			=> 'PunBB\\Module\\Extern\\Controller\\ExternController',
		'help.php'				=> 'PunBB\\Module\\Help\\Controller\\HelpController',
		'index.php'				=> 'PunBB\\Module\\Index\\Controller\\IndexController',
		'login.php'				=> 'PunBB\\Module\\Login\\Controller\\LoginController',
		'misc.php'				=> 'PunBB\\Module\\Misc\\Controller\\MiscController',
		'moderate.php'			=> 'PunBB\\Module\\Moderate\\Controller\\ModerateController',
		'post.php'				=> 'PunBB\\Module\\Post\\Controller\\PostController',
		'profile.php'			=> 'PunBB\\Module\\Profile\\Controller\\ProfileController',
		'register.php'			=> 'PunBB\\Module\\Register\\Controller\\RegisterController',
		'search.php'			=> 'PunBB\\Module\\Search\\Controller\\SearchController',
		'userlist.php'			=> 'PunBB\\Module\\Userlist\\Controller\\UserListController',
		'viewforum.php'			=> 'PunBB\\Module\\Viewforum\\Controller\\ForumController',
		'viewtopic.php'			=> 'PunBB\\Module\\Viewtopic\\Controller\\TopicController',
	);

	/**
	 * `include/common.php` rejects any POST without a valid `csrf_token`. Only
	 * post.php's route opts out, and its controller checks the token itself
	 * for every poster.
	 *
	 * @var list<string>
	 */
	private const SKIPS_GLOBAL_CSRF_GATE = array('post.php');

	/** The installer and the updater run before a usable configuration exists. */
	private const SETUP = array('admin/install.php', 'admin/db_update.php');

	private static function router(): Router {
		return ModuleRegistry::discover(ModuleTree::core(FORUM_ROOT))->router();
	}

	/** @return list<string> the PHP files directly below $dir, relative to FORUM_ROOT, sorted */
	private static function phpFiles(string $dir): array {
		$files = array_map(static fn (string $file): string => substr($file, strlen(FORUM_ROOT)), (array) glob(FORUM_ROOT.$dir.'*.php'));
		sort($files);

		return $files;
	}

	public function testTheRouteMapIsTheAuditedOne(): void {
		$map = array();
		foreach (self::router()->routes() as $route)
			foreach ($route->paths as $path)
				$map[$path] = $route->controller;

		ksort($map, SORT_STRING);

		$this->assertSame(self::ROUTES, $map);
	}

	/**
	 * A feed reader's request is no visit: it leaves the online list and the
	 * last visit alone, as extern.php always did; so does a request for one of
	 * login.php's or misc.php's actions, as the pages always defined.
	 */
	public function testOnlySyndicationAndTheLoginAndMiscActionsAreQuiet(): void {
		$quiet = array();
		foreach (self::router()->routes() as $route)
			if ($route->quiet || $route->quietWith !== array())
				$quiet[$route->paths[0]] = array($route->quiet, $route->quietWith);

		$this->assertSame(array('extern.php' => array(true, array()), 'login.php' => array(false, array('action')), 'misc.php' => array(false, array('action'))), $quiet);
	}

	/** Anything else in the root would be run by the web server without the router. */
	public function testTheRootHoldsTheFrontControllerAndTheLayoutIncludesOnly(): void {
		$this->assertSame(self::ROOT_SCRIPTS, array_values(array_diff(self::phpFiles(''), array('config.php'))));
		$this->assertDirectoryDoesNotExist(FORUM_ROOT.'admin');
	}

	/** Nothing is left of the page scripts the front controller required. */
	public function testNoPageScriptIsLeft(): void {
		$this->assertDirectoryDoesNotExist(FORUM_ROOT.'include/pages');
	}

	public function testOnlyPostPhpSkipsTheGlobalCsrfGate(): void {
		$skipping = array();
		foreach (self::router()->routes() as $route)
			if ($route->checksOwnToken)
				array_push($skipping, ...$route->paths);

		$this->assertSame(self::SKIPS_GLOBAL_CSRF_GATE, $skipping);
	}

	public function testTheGlobalCsrfGateStillRejectsUntokenedPosts(): void {
		$gate = <<<'GATE'
if (!empty($_POST) && (isset($_POST['confirm_cancel']) || !csrf_token_matches($_POST['csrf_token'] ?? null, get_current_url())) && !defined('FORUM_SKIP_CSRF_CONFIRM'))
	csrf_confirm_form();
GATE;

		$this->assertStringContainsString($gate, file_get_contents(FORUM_ROOT.'include/common.php'));
	}

	public function testOnlyTheInstallerAndTheUpdaterRunWithoutTheBoard(): void {
		$setup = array();
		foreach (self::router()->routes() as $route)
			if ($route->setup)
				array_push($setup, ...$route->paths);

		$this->assertSame(self::SETUP, $setup);
	}

	/** Every rule of every SEF scheme reaches a routed page, or the pretty URL it matches is a 404. */
	public function testEveryRewriteRuleTargetsARoutedPage(): void {
		$router = self::router();

		foreach ((array) glob(FORUM_ROOT.'include/url/*/rewrite_rules.php') as $file)
		{
			$forum_rewrite_rules = array();
			require (string) $file;

			foreach ($forum_rewrite_rules as $rule => $target)
			{
				$path = explode('?', $target, 2)[0];

				// A back reference in the file name expands to one of the alternatives its group offers.
				$paths = preg_match('/\$[0-9]+/', $path) === 1 && preg_match('/\(([a-z]+(?:\|[a-z]+)+)\)/', $rule, $group) === 1
					? array_map(static fn (string $alternative): string => (string) preg_replace('/\$[0-9]+/', $alternative, $path), explode('|', $group[1]))
					: array($path);

				foreach ($paths as $expanded)
					$this->assertNotNull($router->match($expanded), basename(dirname((string) $file)).': '.$rule.' reaches '.$expanded.', which no route serves');
			}
		}
	}
}
