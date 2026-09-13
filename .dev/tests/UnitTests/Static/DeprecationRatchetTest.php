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
			'ceiling'		=> 1717,
		),
		'query_build()' => array(
			'replaced by'	=> 'a repository method behind a module\'s Api contract, over prepared statements',
			'marker'		=> 'none yet: the attribute lands with the first repository, as every page, the installer and the updater reach it today',
			'attributes'	=> array(),
			'ceiling'		=> 439,
		),
		'$forum_db outside the bridge' => array(
			'replaced by'	=> 'a repository behind an Api contract, handed in through the constructor',
			'marker'		=> 'none: a global variable carries no attribute',
			'attributes'	=> array(),
			'ceiling'		=> 1121,
		),
		'str_replace on a template marker' => array(
			'replaced by'	=> 'the layout, composing a page template into the chrome template',
			'marker'		=> 'none yet: the sites are entry points and footer.php, which the layout replaces',
			'attributes'	=> array(),
			'ceiling'		=> 86,
		),
	);

	/** Top-level directories that are not the forum's own code. */
	private const NOT_SCANNED = array('vendor', 'cache', 'extensions', 'docs', 'img');

	/** Carries the deprecated mechanism itself; v2.1 deletes it with the entries. */
	private const BRIDGE = 'include/PunBB/Module/LegacyBridge/';

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

	/** @return array<string, int> entry => the sites in $source reaching it */
	private static function sites(string $source): array {
		$tokens = self::tokens($source);
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
		}

		return $sites;
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

		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(FORUM_ROOT.self::BRIDGE, FilesystemIterator::SKIP_DOTS)) as $file)
			$declared += self::deprecatedDeclarations(substr($file->getPathname(), strlen(FORUM_ROOT)), (string) file_get_contents($file->getPathname()));

		ksort($declared);
		$inventoried = array_merge(...array_column(self::DEPRECATED, 'attributes'));
		sort($inventoried);

		$this->assertSame($inventoried, array_keys($declared));

		foreach ($declared as $declaration => $attribute)
			$this->assertMatchesRegularExpression('/^#\[\\\\?Deprecated\(since:\'2\.0\',message:\'[^\']+\'\)\]$/', $attribute, $declaration.' is not marked since 2.0 with a message naming its replacement');
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
// eval($hook); $forum_db->query_build($query);
PHP;

		$this->assertSame(array(
			'eval($hook)'						=> 3,
			'query_build()'						=> 2,
			'$forum_db outside the bridge'		=> 3,
			'str_replace on a template marker'	=> 3,
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
