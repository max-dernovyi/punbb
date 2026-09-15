<?php
/**
 * What v2.0 deprecates, what replaces it, and how many core call sites still
 * reach it. A core caller of a deprecated entry is work not yet done, so each
 * count may only fall: a new site fails the build, and a removed one fails it
 * until the ceiling is lowered to lock the fall in. v2.1 removes every entry.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeprecationRatchetTest extends TestCase {
	/**
	 * entry => what replaces it, how it is marked, the declarations carrying
	 * #[\Deprecated] ("<file>:<function>"), and the core sites reaching it when
	 * the ratchet last moved.
	 *
	 * @var array<string, array{replaced by: string, marker: string, attributes: list<string>, ceiling: int}>
	 */
	private const DEPRECATED = array(
		'eval($hook)' => array(
			'replaced by'	=> 'an observer on the event, or a plugin on the Api contract method, that HookMap::COVERED names for the point',
			'marker'		=> 'get_hook() raises E_USER_DEPRECATED when it hands stored code over; the runners carry #[\Deprecated]. Sites: each eval($hook), and each reference to a runner outside the bridge',
			'attributes'	=> array(
				'include/PunBB/Module/LegacyBridge/Hook/MarkupHookRunner.php:render',
				'include/PunBB/Module/LegacyBridge/Hook/StatementHookRunner.php:run',
			),
			'ceiling'		=> 244,
		),
		'query_build()' => array(
			'replaced by'	=> 'a repository method behind a module\'s Api contract, over prepared statements',
			'marker'		=> 'each driver\'s query_build() carries #[\Deprecated]; include/setup.php routes the notice to the log for the installer and the updater as essentials.php does. Sites: each call',
			'attributes'	=> array(
				'include/dblayer/mysqli.php:query_build',
				'include/dblayer/mysqli_innodb.php:query_build',
				'include/dblayer/pgsql.php:query_build',
				'include/dblayer/sqlite3.php:query_build',
			),
			'ceiling'		=> 89,
		),
		'$forum_db outside the bridge' => array(
			'replaced by'	=> 'a repository behind an Api contract, handed in through the constructor',
			'marker'		=> 'none: a global variable carries no attribute',
			'attributes'	=> array(),
			'ceiling'		=> 227,
		),
		'str_replace on a template marker' => array(
			'replaced by'	=> 'the layout, composing a page template into the chrome template',
			'marker'		=> 'none: the site is message() inside a page whose header the $tpl_main protocol built, which includes footer.php, which raises E_USER_DEPRECATED',
			'attributes'	=> array(),
			'ceiling'		=> 1,
		),
		'header.php and footer.php' => array(
			'replaced by'	=> 'the layout: PunBB\\Module\\Layout\\Chrome\\Layout::open() and PageChrome::close() with the page\'s own regions',
			'marker'		=> 'both files raise E_USER_DEPRECATED when included. Sites: each include or require naming either',
			'attributes'	=> array(),
			'ceiling'		=> 1,
		),
	);

	/** The two files a page built on $tpl_main includes around its own markup. */
	private const CHROME_SCRIPTS = array('header.php', 'footer.php');

	/** Top-level directories that are not the forum's own code. */
	private const NOT_SCANNED = array('vendor', 'cache', 'extensions', 'docs', 'img');

	/** Carries the deprecated mechanism itself; v2.1 deletes it with the entries. */
	private const BRIDGE = 'include/PunBB/Module/LegacyBridge/';

	/** Functions carrying a site that only extension code calls, through the include/functions.php facade. */
	private const CALLED_BY_EXTENSIONS = array('forum_config_add', 'forum_config_remove');

	private const RUNNERS = array('StatementHookRunner', 'MarkupHookRunner');

	private const TEMPLATE_MARKER = '/^([\'"])<!--\s*[a-z_]+\s*-->\1$/';

	/** @var array<string, string>|null path relative to FORUM_ROOT => source */
	private static ?array $sources = null;

	/** @return array<string, string> every core PHP file outside the bridge */
	private static function sources(): array {
		if (self::$sources !== null)
			return self::$sources;

		$files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator(FORUM_ROOT, FilesystemIterator::SKIP_DOTS),
			function (SplFileInfo $file, string $path, RecursiveDirectoryIterator $iterator): bool {
				$name = $file->getFilename();

				if ($iterator->getSubPath() === '' && ($name[0] === '.' || $name === 'config.php' || in_array($name, self::NOT_SCANNED, true)))
					return false;

				return $file->isDir() || $file->getExtension() === 'php';
			}
		));

		self::$sources = array();
		foreach ($files as $file)
		{
			$path = substr($file->getPathname(), strlen(FORUM_ROOT));
			if (!str_starts_with($path, self::BRIDGE))
				self::$sources[$path] = (string) file_get_contents($file->getPathname());
		}

		ksort(self::$sources);

		return self::$sources;
	}

	/** @return list<array{0: int, 1: string}|string> the tokens of $source, without whitespace and comments */
	private static function tokens(string $source): array {
		return array_values(array_filter(token_get_all($source), static fn (array|string $token): bool => !is_array($token) || !in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)));
	}

	private static function is(array|string|null $token, int|string $kind, ?string $text = null): bool {
		if (is_string($kind))
			return $token === $kind;

		return is_array($token) && $token[0] === $kind && ($text === null || $token[1] === $text);
	}

	/** @return array<string, string> path relative to FORUM_ROOT => source, for every file of the bridge */
	private static function bridgeSources(): array {
		$sources = array();
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(FORUM_ROOT.self::BRIDGE, FilesystemIterator::SKIP_DOTS)) as $file)
			$sources[substr($file->getPathname(), strlen(FORUM_ROOT))] = (string) file_get_contents($file->getPathname());

		return $sources;
	}

	/** @return array<string, int> entry => the sites in $source reaching it */
	private static function sites(string $source): array {
		return self::sitesIn(self::tokens($source));
	}

	/** @return array<string, int> entry => the sites among $tokens reaching it */
	private static function sitesIn(array $tokens): array {
		$sites = array_fill_keys(array_keys(self::DEPRECATED), 0);
		$import = false;

		foreach ($tokens as $i => $token)
		{
			// An import names a runner without reaching it.
			if (self::is($token, T_USE) && !self::is($tokens[$i + 1] ?? null, '('))
				$import = true;
			else if ($import && self::is($token, ';'))
				$import = false;

			if (self::is($token, T_EVAL) && self::is($tokens[$i + 1] ?? null, '(') && self::is($tokens[$i + 2] ?? null, T_VARIABLE, '$hook') && self::is($tokens[$i + 3] ?? null, ')'))
				$sites['eval($hook)']++;
			else if (!$import && is_array($token) && in_array($token[0], array(T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED), true) && in_array(substr((string) strrchr('\\'.$token[1], '\\'), 1), self::RUNNERS, true))
				$sites['eval($hook)']++;
			else if (is_array($token) && in_array($token[0], array(T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON), true) && self::is($tokens[$i + 1] ?? null, T_STRING, 'query_build') && self::is($tokens[$i + 2] ?? null, '('))
				$sites['query_build()']++;
			else if (self::is($token, T_VARIABLE, '$forum_db'))
				$sites['$forum_db outside the bridge']++;
			else if (self::is($token, T_VARIABLE, '$GLOBALS') && self::is($tokens[$i + 1] ?? null, '[') && self::is($tokens[$i + 2] ?? null, T_CONSTANT_ENCAPSED_STRING) && trim($tokens[$i + 2][1], '\'"') === 'forum_db')
				$sites['$forum_db outside the bridge']++;
			else if ((self::is($token, T_STRING, 'str_replace') || self::is($token, T_NAME_FULLY_QUALIFIED, '\\str_replace')) && self::is($tokens[$i + 1] ?? null, '(') && self::firstArgumentHasAMarker($tokens, $i + 2))
				$sites['str_replace on a template marker']++;
			else if (is_array($token) && in_array($token[0], array(T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE), true) && self::namesAChromeScript($tokens, $i + 1))
				$sites['header.php and footer.php']++;
		}

		return $sites;
	}

	/** Whether the path of the include starting at $start ends in a literal naming header.php or footer.php. */
	private static function namesAChromeScript(array $tokens, int $start): bool {
		for ($i = $start; isset($tokens[$i]) && $tokens[$i] !== ';'; $i++)
			if (self::is($tokens[$i], T_CONSTANT_ENCAPSED_STRING) && in_array(basename(trim($tokens[$i][1], '\'"')), self::CHROME_SCRIPTS, true))
				return true;

		return false;
	}

	/** Whether the argument starting at $start holds a template marker literal, alone or in an array. */
	private static function firstArgumentHasAMarker(array $tokens, int $start): bool {
		$depth = 0;
		for ($i = $start; isset($tokens[$i]); $i++)
		{
			$token = $tokens[$i];

			if (in_array($token, array('(', '['), true))
				$depth++;
			else if (in_array($token, array(')', ']'), true) && $depth-- === 0)
				return false;
			else if ($token === ',' && $depth === 0)
				return false;
			else if (self::is($token, T_CONSTANT_ENCAPSED_STRING) && preg_match(self::TEMPLATE_MARKER, $token[1]) === 1)
				return true;
		}

		return false;
	}

	/** @return array<string, true> the lower-cased names of the functions $tokens call */
	private static function calledFunctions(array $tokens): array {
		$called = array();
		foreach ($tokens as $i => $token)
		{
			if (!is_array($token) || !in_array($token[0], array(T_STRING, T_NAME_FULLY_QUALIFIED), true) || !self::is($tokens[$i + 1] ?? null, '('))
				continue;

			$before = $tokens[$i - 1] ?? null;
			if (is_array($before) && in_array($before[0], array(T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW), true))
				continue;

			$called[strtolower(ltrim($token[1], '\\'))] = true;
		}

		return $called;
	}

	/**
	 * The top-level functions of $counted that hold a site while nothing reaches them: no code
	 * outside a top-level function of $counted or $callers calls them, nor any function that is
	 * itself reached. A class is taken as reached.
	 *
	 * @param array<string, string> $counted path => source whose sites the ratchet counts
	 * @param array<string, string> $callers path => source that only calls
	 * @return list<string>
	 */
	private static function unreachedSiteFunctions(array $counted, array $callers): array {
		$calls = array();
		$sited = array();
		$reached = array_fill_keys(self::CALLED_BY_EXTENSIONS, true);

		foreach (array_merge(array_values($counted), array_values($callers)) as $n => $source)
		{
			$tokens = self::tokens($source);
			$outside = array();

			for ($i = 0, $depth = 0; isset($tokens[$i]); $i++)
			{
				if ($depth === 0 && self::is($tokens[$i], T_FUNCTION) && self::is($tokens[$i + 1] ?? null, T_STRING))
				{
					$name = strtolower($tokens[$i + 1][1]);
					for ($end = $i; isset($tokens[$end]) && $tokens[$end] !== '{'; $end++);
					for ($open = 0; isset($tokens[$end]); $end++)
					{
						$open += ($tokens[$end] === '{' || self::is($tokens[$end], T_CURLY_OPEN) || self::is($tokens[$end], T_DOLLAR_OPEN_CURLY_BRACES)) ? 1 : ($tokens[$end] === '}' ? -1 : 0);
						if ($open === 0)
							break;
					}

					$body = array_slice($tokens, $i, $end - $i + 1);
					$calls[$name] = self::calledFunctions($body);
					if ($n < count($counted) && array_sum(self::sitesIn($body)) > 0)
						$sited[$name] = true;

					$i = $end;
					continue;
				}

				if ($tokens[$i] === '{' || self::is($tokens[$i], T_CURLY_OPEN) || self::is($tokens[$i], T_DOLLAR_OPEN_CURLY_BRACES))
					$depth++;
				else if ($tokens[$i] === '}')
					$depth--;

				$outside[] = $tokens[$i];
			}

			$reached += self::calledFunctions($outside);
		}

		for ($queue = array_keys($reached); $queue !== array();)
		{
			foreach ($calls[array_pop($queue)] ?? array() as $name => $true)
			{
				if (!isset($reached[$name]))
				{
					$reached[$name] = true;
					$queue[] = $name;
				}
			}
		}

		return array_keys(array_diff_key($sited, $reached));
	}

	/** @return array<string, int> entry => the core sites reaching it */
	private static function counts(): array {
		$counts = array_fill_keys(array_keys(self::DEPRECATED), 0);
		foreach (self::sources() as $source)
			foreach (self::sites($source) as $entry => $sites)
				$counts[$entry] += $sites;

		return $counts;
	}

	/**
	 * @return array<string, string> "<file>:<function>" => the attribute's source, for every
	 *                               declaration in $source carrying #[\Deprecated]
	 */
	private static function deprecatedDeclarations(string $file, string $source): array {
		$tokens = self::tokens($source);
		$found = array();

		foreach ($tokens as $i => $token)
		{
			if (!self::is($token, T_ATTRIBUTE))
				continue;

			$name = $tokens[$i + 1] ?? null;
			if (!is_array($name) || !in_array($name[1], array('Deprecated', '\\Deprecated'), true))
				continue;

			$attribute = '#[';
			for ($j = $i + 1, $depth = 0; isset($tokens[$j]) && !($tokens[$j] === ']' && $depth === 0); $j++)
			{
				$depth += in_array($tokens[$j], array('(', '['), true) ? 1 : (in_array($tokens[$j], array(')', ']'), true) ? -1 : 0);
				$attribute .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
			}

			for (; isset($tokens[$j]) && !self::is($tokens[$j], T_FUNCTION); $j++);

			$found[$file.':'.($tokens[$j + 1][1] ?? '')] = $attribute.']';
		}

		return $found;
	}

	/** @return array<string, array{string}> */
	public static function entryProvider(): array {
		return array_combine(array_keys(self::DEPRECATED), array_map(static fn (string $entry): array => array($entry), array_keys(self::DEPRECATED)));
	}

	#[DataProvider('entryProvider')]
	public function testTheCoreSitesReachingADeprecatedEntryNeverRise(string $entry): void {
		$count = self::counts()[$entry];
		$ceiling = self::DEPRECATED[$entry]['ceiling'];

		$this->assertLessThanOrEqual($ceiling, $count, sprintf('%d core sites reach %s, up from %d: use %s instead', $count, $entry, $ceiling, self::DEPRECATED[$entry]['replaced by']));
		$this->assertSame($ceiling, $count, sprintf('%s fell to %d sites: lower its ceiling to %d so the fall stays', $entry, $count, $count));
	}

	/** The inventory lives here, not in prose: every #[\Deprecated] in the tree is in it, since 2.0 and naming its replacement. */
	public function testEveryDeprecatedAttributeIsInventoriedWithItsReplacement(): void {
		$declared = array();
		foreach (self::sources() as $file => $source)
			$declared += self::deprecatedDeclarations($file, $source);

		foreach (self::bridgeSources() as $file => $source)
			$declared += self::deprecatedDeclarations($file, $source);

		ksort($declared);
		$inventoried = array_merge(...array_column(self::DEPRECATED, 'attributes'));
		sort($inventoried);

		$this->assertSame($inventoried, array_keys($declared));

		foreach ($declared as $declaration => $attribute)
			$this->assertMatchesRegularExpression('/^#\[\\\\?Deprecated\(since:\'2\.0\',message:\'[^\']+\'\)\]$/', $attribute, $declaration.' is not marked since 2.0 with a message naming its replacement');
	}

	/** The floor: a site in a function nothing calls is a legacy path to delete, not a site to count. */
	public function testEverySiteLeftIsInCodeSomethingStillReaches(): void {
		$this->assertSame(array(), self::unreachedSiteFunctions(self::sources(), self::bridgeSources()), 'nothing calls these functions, yet they reach a deprecated entry: delete them and lower the ceilings');
	}

	public function testTheReachScanFollowsCallsAndIgnoresTheRest(): void {
		$legacy = <<<'PHP'
<?php
require FORUM_ROOT.'include/common.php';
function used() { helper(); return ($hook = get_hook('a')) ? eval($hook) : null; }
function helper() { global $forum_db; }
function recursive() { recursive(); global $forum_db; }
function called_by_dead() { global $forum_db; }
function dead() { called_by_dead(); }
function method_named() { global $forum_db; }
function quiet() {}
function forum_config_add() { global $forum_db; }
used();
PHP;
		$bridge = <<<'PHP'
<?php
namespace PunBB\Module\LegacyBridge;
final class Caller {
	public function run(object $x): void { $x->method_named(); \PunBB\helper::method_named(); new method_named(); }
}
PHP;

		$this->assertSame(array('recursive', 'called_by_dead', 'method_named'), self::unreachedSiteFunctions(array('legacy.php' => $legacy), array('bridge.php' => $bridge)));
	}

	/** The counters are worth nothing if they miss a shape the tree uses, or count one that reaches nothing. */
	public function testTheCountersSeeEverySiteShape(): void {
		$source = <<<'PHP'
<?php
use PunBB\Module\LegacyBridge\Hook\MarkupHookRunner;
($hook = get_hook('x')) ? eval($hook) : null;
eval($ext_data['extension']['install']);
$runner = $forum_container->get(\PunBB\Module\LegacyBridge\Hook\StatementHookRunner::class);
$markup = function () use ($forum_container) { return $forum_container->get(MarkupHookRunner::class); };
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
$sql = $this->query_build($query, true);
function query_build($query) { return 'query_build'; }
global $forum_db;
$GLOBALS['forum_db']->escape('x');
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
$tpl_main = \str_replace(array('<!-- forum_head -->', "<!-- forum_title -->"), $parts, $tpl_main);
$text = str_replace(forum_trim('<!-- forum_main -->'), '', $text);
$text = str_replace('<!-- not a marker', '<!-- forum_main -->', $text);
require FORUM_ROOT.'header.php';
include FORUM_ROOT . 'footer.php';
require_once FORUM_ROOT.'include/common.php';
$page = 'header.php';
// eval($hook); $forum_db->query_build($query); require FORUM_ROOT.'footer.php';
PHP;

		$this->assertSame(array(
			'eval($hook)'						=> 3,
			'query_build()'						=> 2,
			'$forum_db outside the bridge'		=> 3,
			'str_replace on a template marker'	=> 3,
			'header.php and footer.php'			=> 2,
		), self::sites($source));
	}

	public function testTheAttributeScanNamesTheDeclarationAndKeepsItsArguments(): void {
		$source = <<<'PHP'
<?php
final class Runner {
	#[\Deprecated(since: '2.0', message: 'use the event')]
	public function run(array $exposed = []): mixed { return null; }

	#[\SensitiveParameter]
	public function keep(): void {}
}
#[Deprecated(since: '2.0', message: 'use a repository')]
function legacy_helper() {}
PHP;

		$this->assertSame(array(
			'x.php:run'				=> '#[\\Deprecated(since:\'2.0\',message:\'use the event\')]',
			'x.php:legacy_helper'	=> '#[Deprecated(since:\'2.0\',message:\'use a repository\')]',
		), self::deprecatedDeclarations('x.php', $source));
	}
}
