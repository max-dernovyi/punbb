<?php
/**
 * A rewrite rule only ever reaches a routed page in the forum root.
 *
 * The front controller runs a path no route serves through a table of rewrite
 * rules and routes whatever the winning replacement names before the "?".
 * The shipped rules all replace into a fixed filename, but the table is data:
 * the `re_rewrite_rules` hook hands it to any installed extension, and the
 * request URI reaching the match is urldecode()d, so the sink is what has to
 * hold. forum_rewrite_target() is that sink, and the route map is behind it.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Modules\ModuleRegistry;

class RewriteTargetTest extends TestCase
{
	/**
	 * @return array<string, array{string, string}>
	 */
	public static function acceptedProvider(): array
	{
		return array(
			'with a query'  => array('viewtopic.php?id=1&p=2', 'viewtopic.php'),
			// The shipped rules are /i and substitute the request's casing, so
			// /Login expands to Login.php, which the route map does not serve.
			'mixed case'    => array('Login.php', 'Login.php'),
			'bare'          => array('userlist.php', 'userlist.php'),
			'underscore'    => array('extern.php?action=feed', 'extern.php'),
			'empty query'   => array('index.php?', 'index.php'),
		);
	}

	#[DataProvider('acceptedProvider')]
	public function testAnEntryPointIsAccepted(string $rewritten, string $expected): void
	{
		$this->assertSame($expected, forum_rewrite_target($rewritten));
	}

	/**
	 * Everything a rule could be made to produce that is not a plain entry
	 * point in the forum root.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function refusedProvider(): array
	{
		return array(
			'parent traversal'   => array('../config.php'),
			'deep traversal'     => array('../../../../etc/passwd'),
			'subdirectory'       => array('include/common.php'),
			'absolute'           => array('/etc/passwd'),
			'backslash'          => array('..\\config.php'),
			'wrapper'            => array('php://filter/resource=config.php'),
			'remote'             => array('https://evil.com/x.php'),
			'nul byte'           => array("index.php\0.gif"),
			'uppercase extension' => array('Index.PHP'),
			'no extension'       => array('config'),
			'other extension'    => array('config.php.bak'),
			'dot file'           => array('.htaccess'),
			'empty'              => array(''),
			'query only'         => array('?id=1'),
			'trailing space'     => array('index.php '),
			'not a string'       => array(null),
		);
	}

	#[DataProvider('refusedProvider')]
	public function testAnythingElseIsRefused(mixed $rewritten): void
	{
		$this->assertFalse(forum_rewrite_target($rewritten));
	}

	/**
	 * The five shipped rulesets. Every replacement's path portion has to be a
	 * filename template: no separator, and no back reference that could carry
	 * one in, since the capture groups feeding it are bounded alternations.
	 *
	 * @return array<string, array{string}>
	 */
	public static function rulesetProvider(): array
	{
		$sets = array();

		foreach ((array) glob(FORUM_ROOT.'include/url/*/rewrite_rules.php') as $file)
			$sets[basename(dirname((string) $file))] = array((string) $file);

		return $sets;
	}

	#[DataProvider('rulesetProvider')]
	public function testEveryShippedRuleRoutesToAnEntryPoint(string $file): void
	{
		$forum_rewrite_rules = array();
		require $file;

		$this->assertNotEmpty($forum_rewrite_rules, $file.': no rules');

		foreach ($forum_rewrite_rules as $rule => $rewrite_to)
		{
			$target = explode('?', (string) $rewrite_to, 2)[0];

			$this->assertMatchesRegularExpression('/\A[a-z0-9_$]+\.php\z/', $target,
				$file.': "'.$rule.'" routes to "'.$target.'", which is not a bare entry point');

			// Every back reference in the path portion must come from a group
			// that can only match a literal, so the expansion is one of a
			// fixed set of filenames the forum ships.
			if (preg_match_all('/\$([0-9]+)/', $target, $refs))
				foreach ($refs[1] as $index)
					$this->assertMatchesRegularExpression('/\(([a-z]+\|)+[a-z]+\)/', $rule,
						$file.': "'.$rule.'" expands $'.$index.' into the filename without a bounded alternation');
		}
	}

	/** Each of those filenames, once expanded, is a page the route map serves. */
	#[DataProvider('rulesetProvider')]
	public function testEveryShippedRuleNamesARoutedPage(string $file): void
	{
		$router = ModuleRegistry::discover(FORUM_ROOT.'include/PunBB/Module', 'PunBB\\Module\\')->router();

		$forum_rewrite_rules = array();
		require $file;

		foreach ($forum_rewrite_rules as $rule => $rewrite_to)
		{
			$target = explode('?', (string) $rewrite_to, 2)[0];
			$candidates = array($target);

			// Expand "$1" against the alternations the pattern offers.
			if (strpos($target, '$') !== false && preg_match_all('/\(([a-z]+(?:\|[a-z]+)+)\)/', $rule, $groups))
			{
				$candidates = array();
				foreach (explode('|', $groups[1][0]) as $alternative)
					$candidates[] = (string) preg_replace('/\$[0-9]+/', $alternative, $target);
			}

			foreach ($candidates as $candidate)
			{
				$this->assertIsString(forum_rewrite_target($candidate), $rule.': "'.$candidate.'" is refused by the sink');
				$this->assertNotNull($router->match($candidate), $rule.': "'.$candidate.'" is not routed');
			}
		}
	}

	/**
	 * Source guard: the front controller routes the validated value, never the
	 * raw one, and requires nothing but the bootstrap a route runs on.
	 */
	public function testTheFrontControllerRoutesTheValidatedTarget(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'index.php');

		$this->assertStringContainsString('$rewrite_target = $forum_rewrite !== null ? forum_rewrite_target($forum_rewrite->target) : false;', $source);
		$this->assertStringContainsString('$forum_route = $rewrite_target !== false ? $forum_router->match($rewrite_target) : null;', $source);
		$this->assertSame(array('FORUM_ROOT.\'include/autoload.php\'', 'FORUM_ROOT.\'include/essentials.php\'',
			'FORUM_ROOT.\'include/url/\'.$forum_config[\'o_sef\'].\'/rewrite_rules.php\'', 'FORUM_ROOT.\'include/url/Default/rewrite_rules.php\'',
			'FORUM_ROOT.\'include/setup.php\'', 'FORUM_ROOT.\'include/common.php\''),
			preg_match_all('/\brequire\s+([^;]+);/', $source, $matches) > 0 ? $matches[1] : array());
	}
}
