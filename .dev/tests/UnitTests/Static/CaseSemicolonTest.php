<?php
/**
 * No switch case may be terminated with a semicolon.
 *
 * `case 'x';` has always been an accepted alias for `case 'x':`; PHP 8.5
 * deprecates it, and a deprecation emitted at compile time reaches the browser
 * before any header is sent, so one such case turns every subsequent
 * `header()` call on that page into a warning. `php -l` says nothing about it
 * on 8.4, so the tree is scanned by token instead.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class CaseSemicolonTest extends TestCase {
	/** Trees that are not ours. */
	private const SKIP = array('vendor', '.git', 'cache', 'img', 'node_modules', 'extensions', 'tmp');

	public function testNoFileTerminatesACaseWithASemicolon(): void {
		$found = array();

		foreach ($this->phpFiles() as $relative)
			foreach (self::semicolonCases(file_get_contents(FORUM_ROOT.$relative)) as $line)
				$found[] = $relative.':'.$line;

		sort($found);

		$this->assertSame(array(), $found, 'use "case x:", not "case x;" — deprecated as of PHP 8.5');
	}

	public function testTheScannerSeesTheDeprecatedForm(): void {
		$this->assertSame(array(3), self::semicolonCases("<?php\nswitch (\$x) {\n\tcase 'a';\n\t\tbreak;\n}\n"));
	}

	public function testTheScannerSeesADefaultTerminatedWithASemicolon(): void {
		$this->assertSame(array(3), self::semicolonCases("<?php\nswitch (\$x) {\n\tdefault;\n}\n"));
	}

	/** A ternary and an interpolated brace both hide the terminator from a naive scan. */
	public function testTheScannerSeesTheDeprecatedFormBehindAnExpression(): void {
		$this->assertSame(array(3), self::semicolonCases("<?php\nswitch (\$x) {\n\tcase \$a ? 1 : 2;\n\t\tbreak;\n}\n"));
		$this->assertSame(array(3), self::semicolonCases("<?php\nswitch (\$x) {\n\tcase \"{\$a}b\";\n\t\tbreak;\n}\n"));
	}

	/** The forms that must not be reported: the colon, a ternary, an enum case, a match arm. */
	public function testTheScannerPassesValidCode(): void {
		$this->assertSame(array(), self::semicolonCases("<?php\nswitch (\$x) {\n\tcase 'a':\n\t\tbreak;\n}\n"));
		$this->assertSame(array(), self::semicolonCases("<?php\nswitch (\$x) {\n\tcase \$a ? 1 : 2:\n\t\tbreak;\n}\n"));
		$this->assertSame(array(), self::semicolonCases("<?php\nswitch (\$x) {\n\tcase \"{\$a}b\":\n\t\tbreak;\n}\n"));
		$this->assertSame(array(), self::semicolonCases("<?php\nswitch (\$x) {\n\tdefault:\n\t\tbreak;\n}\n"));
		$this->assertSame(array(), self::semicolonCases("<?php\n\$y = match (\$x) {\n\tdefault => 1,\n};\n"));
		$this->assertSame(array(), self::semicolonCases("<?php\nenum E: string {\n\tcase A = 'a';\n}\n"));
	}

	/**
	 * Lines carrying a `case <expr>;` — an expression closed by a semicolon
	 * before any colon at the top level of the case. Enum cases, which
	 * legitimately end that way, are skipped by tracking the brace they open.
	 *
	 * @return list<int>
	 */
	private static function semicolonCases(string $source): array {
		$tokens = token_get_all($source);
		$found = array();
		$enums = array();       // one entry per open brace: is it an enum body?
		$pending = false;       // the next brace opens an enum body

		foreach ($tokens as $index => $token) {
			if (is_array($token) && $token[0] === T_ENUM) {
				$pending = true;
				continue;
			}

			if ($token === '{' || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
				$enums[] = $pending;
				$pending = false;
				continue;
			}

			if ($token === '}') {
				array_pop($enums);
				continue;
			}

			if (!is_array($token) || end($enums) === true)
				continue;

			// `default` carries no expression, so only the token after it matters.
			if ($token[0] === T_DEFAULT) {
				if (self::nextToken($tokens, $index) === ';')
					$found[] = $token[2];

				continue;
			}

			if ($token[0] !== T_CASE)
				continue;

			if (self::endsWithSemicolon($tokens, $index))
				$found[] = $token[2];
		}

		return $found;
	}

	/**
	 * Whether the case starting at $index closes on a semicolon rather than a
	 * colon. Depth keeps a colon or semicolon nested in the case expression —
	 * `case f(a ?: b):` — from ending the scan early.
	 *
	 * @param list<array{int, string, int}|string> $tokens
	 */
	private static function endsWithSemicolon(array $tokens, int $index): bool {
		$depth = 0;
		$ternary = 0;

		for ($i = $index + 1, $count = count($tokens); $i < $count; ++$i) {
			$token = $tokens[$i];

			// An interpolated string opens its brace as a token array, not '{'.
			if (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))
				++$depth;
			else if (in_array($token, array('(', '[', '{'), true))
				++$depth;
			else if (in_array($token, array(')', ']', '}'), true))
				--$depth;
			else if ($depth === 0 && $token === '?')
				++$ternary;
			else if ($depth === 0 && $token === ';')
				return true;
			else if ($depth === 0 && $token === ':') {
				if ($ternary === 0)
					return false;

				--$ternary;
			}
		}

		return false;
	}

	/**
	 * The next token that is neither whitespace nor a comment.
	 *
	 * @param list<array{int, string, int}|string> $tokens
	 * @return array{int, string, int}|string|null
	 */
	private static function nextToken(array $tokens, int $index) {
		for ($i = $index + 1, $count = count($tokens); $i < $count; ++$i) {
			$token = $tokens[$i];

			if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true))
				continue;

			return $token;
		}

		return null;
	}

	/**
	 * Repository-relative paths of every PHP file, this test aside.
	 *
	 * @return list<string>
	 */
	private function phpFiles(): array {
		$root = realpath(FORUM_ROOT);
		$self = realpath(__FILE__);
		$found = array();

		$dirs = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
		$filter = new RecursiveCallbackFilterIterator($dirs, static function ($file) use ($root) {
			$relative = ltrim(str_replace($root, '', $file->getPathname()), '/');

			return !in_array(explode('/', $relative)[0], self::SKIP, true);
		});

		foreach (new RecursiveIteratorIterator($filter) as $file) {
			if ($file->getRealPath() === $self || $file->getExtension() !== 'php')
				continue;

			$found[] = ltrim(str_replace($root, '', $file->getPathname()), '/');
		}

		sort($found);

		return $found;
	}
}
