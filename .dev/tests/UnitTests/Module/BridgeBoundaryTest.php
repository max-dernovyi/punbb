<?php
/**
 * The bridge is one module and the dependency runs one way: it reaches into
 * the core and the legacy hook mechanism, and nothing else in the new core —
 * nor a module written against it — reaches into the bridge or into that
 * mechanism. v2.1 deletes the module and nothing else notices.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BridgeBoundaryTest extends TestCase {
	private const BRIDGE = 'LegacyBridge';

	private const BRIDGE_DIRECTORY = 'include/PunBB/Module/'.self::BRIDGE.'/';

	/** @return array<string, array{string}> */
	public static function treeProvider(): array {
		return array(
			'forum'		=> array('include/PunBB/'),
			'fixtures'	=> array('.dev/tests/fixtures/modules/'),
		);
	}

	/** @return list<string> every PHP file below $tree outside the bridge, relative to FORUM_ROOT */
	private static function files(string $tree): array {
		$files = array();
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(FORUM_ROOT.$tree, FilesystemIterator::SKIP_DOTS)) as $file)
		{
			$path = substr($file->getPathname(), strlen(FORUM_ROOT));

			// Every file, not only .php: a .phtml template is executed PHP and is
			// the likeliest place to want a markup hook, which is the thing the
			// bridge exists to replace. token_get_all() on a file with no PHP tag
			// yields inline HTML and matches nothing, so scanning wide is free.
			if (!str_starts_with($path, self::BRIDGE_DIRECTORY))
				$files[] = $path;
		}

		sort($files);

		return $files;
	}

	/**
	 * Every mention of the bridge — a name with LegacyBridge as a segment, in a
	 * use statement, a qualified name or a string — and every use of the hook
	 * mechanism: eval, get_hook(), the ext_info stack.
	 *
	 * @return list<string>
	 */
	private static function references(string $source): array {
		$found = array();
		foreach (token_get_all($source) as $token)
		{
			if (!is_array($token))
				continue;

			[$kind, $text, $line] = $token;

			if ($kind === T_EVAL)
				$found[] = $line.': eval';
			else if (in_array($kind, array(T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE), true) && in_array(self::BRIDGE, explode('\\', $text), true))
				$found[] = $line.': '.$text;
			else if ($kind === T_STRING && $text === 'get_hook' || in_array($kind, array(T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE), true) && preg_match('/\\\\get_hook$/', $text) === 1)
				$found[] = $line.': '.$text;
			else if (in_array($kind, array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML), true) && preg_match('/\b'.self::BRIDGE.'\b|\bget_hook\b|ext_info_stack/', $text) === 1)
				$found[] = $line.': '.$text;
			else if ($kind === T_VARIABLE && $text === '$ext_info_stack')
				$found[] = $line.': '.$text;
		}

		return $found;
	}

	/** The scanner has to see every form a reference takes, or the boundary guards nothing. */
	public function testTheScannerSeesEveryFormOfReference(): void {
		$source = <<<'PHP'
<?php
namespace PunBB\Module\Topic;
use PunBB\Module\LegacyBridge\Hook\StatementHookRunner;
use PunBB\Module\{LegacyBridge\Hook\MarkupHookRunner};
final class Probe {
	public function dependencies(): array { return array('LegacyBridge'); }
	public function f(): void {
		\PunBB\Module\LegacyBridge\Hook\HookMap::COVERED;
		$id = 'PunBB\\Module\\LegacyBridge\\Hook\\HookMap';
		($hook = get_hook('x')) ? eval($hook) : null;
		\get_hook('y');
		$GLOBALS['ext_info_stack'][] = array();
	}
}
PHP;

		$this->assertSame(array(
			'3: PunBB\\Module\\LegacyBridge\\Hook\\StatementHookRunner',
			'4: LegacyBridge\\Hook\\MarkupHookRunner',
			'6: \'LegacyBridge\'',
			'8: \\PunBB\\Module\\LegacyBridge\\Hook\\HookMap',
			'9: \'PunBB\\\\Module\\\\LegacyBridge\\\\Hook\\\\HookMap\'',
			'10: get_hook',
			'10: eval',
			'11: \\get_hook',
			'12: \'ext_info_stack\'',
		), self::references($source));

		$this->assertSame(array(), self::references("<?php\nnamespace PunBB\\Module\\Legacy;\nfinal class Bridges { public function hook(): string { return 'legacy bridge'; } }\n"));
	}

	#[DataProvider('treeProvider')]
	public function testNothingOutsideTheBridgeReachesItOrTheHookMechanism(string $tree): void {
		$files = self::files($tree);
		$this->assertNotEmpty($files);

		$problems = array();
		foreach ($files as $file)
			foreach (self::references((string) file_get_contents(FORUM_ROOT.$file)) as $reference)
				$problems[] = $file.':'.$reference;

		$this->assertSame(array(), $problems, "outside the bridge module:\n".implode("\n", $problems));
	}

	public function testTheBridgeIsOneModuleThatReachesTheHookMechanism(): void {
		$this->assertFileExists(FORUM_ROOT.self::BRIDGE_DIRECTORY.'Module.php');

		$references = array();
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(FORUM_ROOT.self::BRIDGE_DIRECTORY, FilesystemIterator::SKIP_DOTS)) as $file)
			$references = array_merge($references, self::references((string) file_get_contents($file->getPathname())));

		$this->assertNotEmpty(preg_grep('/: eval$/', $references), 'the bridge no longer evaluates stored code; the scanner is looking in the wrong place');
		$this->assertNotEmpty(preg_grep('/: \\\\get_hook$/', $references));
	}
}
