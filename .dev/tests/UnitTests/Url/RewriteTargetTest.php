<?php
/**
 * rewrite.php only ever require()s an entry point in the forum root.
 *
 * The SEF front controller runs the request URI through a table of rewrite
 * rules and require()s whatever the winning replacement names before the "?".
 * The shipped rules all replace into a fixed filename, but the table is data:
 * the `re_rewrite_rules` hook hands it to any installed extension, and the
 * request URI reaching the match is urldecode()d, so the sink is what has to
 * hold. forum_rewrite_target() is that sink.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
			// /Login expands to Login.php. rewrite.php still has to find the
			// file before it requires it.
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

	/** Each of those filenames, once expanded, is a file that exists. */
	#[DataProvider('rulesetProvider')]
	public function testEveryShippedRuleNamesAFileThatExists(string $file): void
	{
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
				$this->assertFileExists(FORUM_ROOT.$candidate, $rule.': "'.$candidate.'" does not exist');
			}
		}
	}

	/**
	 * Source guard: rewrite.php must require the validated value, not the raw
	 * one. The pre-fix line is the control.
	 */
	public function testRewritePhpRequiresTheValidatedTarget(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'rewrite.php');

		$this->assertStringNotContainsString('require FORUM_ROOT.$url_parts[0];', $source,
			'rewrite.php is back to requiring the rule output unchecked');
		$this->assertStringContainsString('$rewrite_target = forum_rewrite_target($url_parts[0]);', $source);
		$this->assertStringContainsString('require FORUM_ROOT.$rewrite_target;', $source);
		$this->assertStringContainsString('if ($rewrite_target === false || !file_exists(FORUM_ROOT.$rewrite_target))', $source);
	}
}
