<?php
/**
 * Every public URL still reaches the page that answers it: the plain paths
 * through the route map, the pretty ones through their scheme's rewrite rules
 * and then the route map, as index.php resolves them. route_map.json lists the
 * URLs and what each answered with; one recorded as a 404 must not resolve, and
 * every other one must.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Routing\RewriteRules;

class RouteMapTest extends TestCase {
	/** The rewrite rules of one URL scheme, in their declared order. */
	private static function schemeRules(string $scheme): array {
		$forum_rewrite_rules = array();
		require FORUM_ROOT.'include/url/'.$scheme.'/rewrite_rules.php';

		return $forum_rewrite_rules;
	}

	/** The index of the first rule matching $path, or -1. */
	private static function matchedRule(array $rules, string $path): int {
		$request_uri = (string) strtok(urldecode($path), '?');

		foreach (array_keys($rules) as $index => $pattern)
			if (preg_match($pattern, $request_uri))
				return $index;

		return -1;
	}

	/** The own path of the page index.php serves $url with under $scheme, or null for the 404 page. */
	private static function resolve(string $url, string $scheme): ?string {
		static $router = null;
		$router ??= ModuleRegistry::discover(FORUM_ROOT.'include/PunBB/Module', 'PunBB\\Module\\')->router();

		$request = Request::fromGlobals(array('SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/'.$url), array(), array(), array());
		$route = $router->match($request->path);

		if ($route === null)
		{
			$rewrite = (new RewriteRules(self::schemeRules($scheme)))->rewrite($request->path);
			$target = $rewrite !== null ? forum_rewrite_target($rewrite->target) : false;
			$route = $target !== false ? $router->match($target) : null;
		}

		return $route?->paths[0];
	}

	/** The entry point that answered $url before the front controller: by its path, or by the target of the first rule matching it. */
	private static function servedBefore(string $url, string $scheme): string {
		$path = (string) strtok(urldecode($url), '?');
		$rules = self::schemeRules($scheme);
		$index = self::matchedRule($rules, $path);

		if (preg_match('#\A(?:admin/)?[a-z_]+\.php\z#', $path) !== 1 && $index >= 0)
		{
			$pattern = array_keys($rules)[$index];
			$path = explode('?', (string) preg_replace($pattern, $rules[$pattern], $path), 2)[0];
		}

		return $path === 'admin/' ? 'admin/index.php' : ($path === '' ? 'index.php' : $path);
	}

	public function testEveryUrlTheMatrixRequestedResolvesToThePageThatAnsweredIt(): void {
		$index = json_decode((string) file_get_contents(FORUM_ROOT.'.dev/tests/fixtures/route_map.json'), true);
		$this->assertIsArray($index);

		$resolved = 0;
		foreach ($index['requests'] as $request)
		{
			$url = str_replace(array('{base_url}/', '{search_id}'), array('', '1804289383'), $request['url']);
			$page = self::resolve($url, $request['scheme']);

			if ($request['status'] === 404)
				$this->assertNull($page, $request['id'].' answered 404, and now resolves to '.$page);
			else
			{
				$this->assertSame(self::servedBefore($url, $request['scheme']), $page, $request['id'].' does not resolve to the page that answered it');
				++$resolved;
			}
		}

		$this->assertSame(1055, $resolved);
	}
}
