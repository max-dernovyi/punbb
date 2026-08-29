<?php
/**
 * Regression guard for the "fix nulls at the source" rule.
 *
 * An absent $_POST/$_GET key is null, and PHP 8.1 deprecates null reaching a
 * non-nullable internal parameter. The entry points therefore normalise the
 * key where they read it, rather than casting inside every helper call. This
 * scans the source for a superglobal read handed straight to one of the string
 * helpers and fails on a read that carries neither a default nor a guard.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class RequestKeyGuardTest extends TestCase {
	/** Helpers whose parameter is a non-nullable string once it reaches PHP. */
	private const CONSUMERS = array(
		'forum_trim', 'forum_linebreaks', 'forum_htmlencode', 'forum_hash',
		'strtolower', 'strtoupper', 'substr', 'substr_count', 'str_replace',
		'preg_replace', 'preg_match', 'sha1', 'md5', 'trim', 'explode',
	);

	/** Internal functions that raise a TypeError on an array argument. */
	private const INTERNAL_CONSUMERS = array(
		'strtolower', 'strtoupper', 'strlen', 'substr', 'substr_count',
		'str_replace', 'preg_replace', 'preg_match', 'preg_split',
		'sha1', 'md5', 'trim', 'explode', 'htmlspecialchars',
	);

	/** How far back a guard for the same key may sit. */
	private const GUARD_WINDOW = 10;

	/** @return list<string> every PHP file the forum serves */
	private function sources(): array {
		$files = array_merge(
			(array)glob(FORUM_ROOT.'*.php'),
			(array)glob(FORUM_ROOT.'admin/*.php'),
			(array)glob(FORUM_ROOT.'include/*.php')
		);

		return array_values(array_filter($files, 'is_string'));
	}

	/** An isset()/empty() on the same key a few lines up covers the read below it. */
	private function guardedEarlier(array $lines, int $index, string $read): bool {
		$quoted = preg_quote($read, '/');

		for ($back = max(0, $index - self::GUARD_WINDOW); $back < $index; $back++)
			if (preg_match('/\b(?:isset|empty|array_key_exists)\s*\([^)]*'.$quoted.'/', $lines[$back]))
				return true;

		return false;
	}

	public function testNoEntryPointHandsAnUnguardedRequestKeyToAStringHelper(): void {
		$pattern = '/\b(?:'.implode('|', self::CONSUMERS).')\s*\(\s*(\$_(?:POST|GET|COOKIE|REQUEST)\[[^\]]+\])/';
		$offenders = array();

		foreach ($this->sources() as $file)
		{
			$name = substr($file, strlen(FORUM_ROOT));
			$lines = explode("\n", (string)file_get_contents($file));

			foreach ($lines as $number => $line)
			{
				if (!preg_match($pattern, $line, $match))
					continue;

				// A default on the read itself, or a guard around it.
				if (strpos($line, '??') !== false || strpos($line, 'isset(') !== false)
					continue;

				if ($this->guardedEarlier($lines, $number, $match[1]))
					continue;

				$offenders[] = $name.':'.($number + 1).' '.trim($line);
			}
		}

		$this->assertSame(array(), $offenders, "unguarded request keys:\n".implode("\n", $offenders));
	}

	/**
	 * isset() only rules out null. An array-valued query key still reaches the
	 * parameter, and an internal function raises an uncaught TypeError on it,
	 * so these consumers need a type check, not just a presence check.
	 */
	public function testNoEntryPointHandsAnUntypedRequestKeyToAnInternalFunction(): void {
		$pattern = '/\b(?:'.implode('|', self::INTERNAL_CONSUMERS).')\s*\(\s*(?:[^()]*,\s*)?(\$_(?:POST|GET|COOKIE|REQUEST)\[[^\]]+\])/';
		$offenders = array();

		foreach ($this->sources() as $file)
		{
			$name = substr($file, strlen(FORUM_ROOT));
			$lines = explode("\n", (string)file_get_contents($file));

			foreach ($lines as $number => $line)
			{
				if (!preg_match($pattern, $line, $match))
					continue;

				if ($this->typedEarlier($lines, $number, $match[1]))
					continue;

				$offenders[] = $name.':'.($number + 1).' '.trim($line);
			}
		}

		$this->assertSame(array(), $offenders, "untyped request keys:\n".implode("\n", $offenders));
	}

	/** A type check on the same key, on this line or a few lines up. */
	private function typedEarlier(array $lines, int $index, string $read): bool {
		$quoted = preg_quote($read, '/');

		for ($back = max(0, $index - self::GUARD_WINDOW); $back <= $index; $back++)
			if (preg_match('/\b(?:is_string|is_scalar|is_array|is_numeric|intval)\s*\(\s*'.$quoted.'/', $lines[$back]))
				return true;

		return false;
	}

	/** The scanner is worthless if its pattern cannot see an offender. */
	public function testTheScannerRecognisesAnUnguardedRead(): void {
		$pattern = '/\b(?:'.implode('|', self::CONSUMERS).')\s*\(\s*(\$_(?:POST|GET|COOKIE|REQUEST)\[[^\]]+\])/';

		$this->assertSame(1, preg_match($pattern, '	$name = forum_trim($_POST[\'req_username\']);'));
		$this->assertSame(0, preg_match($pattern, '	$name = forum_trim($username);'));
	}

	/**
	 * A default is only a fix when the absent value is harmless. prune_comply
	 * defaulted prune_from to 'all', so a truncated POST pruned every forum.
	 */
	public function testPruneRejectsAnAbsentTargetInsteadOfDefaultingToEveryForum(): void {
		$source = (string)file_get_contents(FORUM_ROOT.'admin/prune.php');

		$this->assertStringNotContainsString('$_POST[\'prune_from\'] ?? \'all\'', $source);
		$this->assertStringContainsString('if (!isset($_POST[\'prune_from\'])', $source);
	}

	/**
	 * A cookie sent as name[key]=v is an array, and every read of one feeds a
	 * string-typed internal function.
	 */
	public function testEveryCookieReadChecksForAString(): void {
		$offenders = array();

		foreach ($this->sources() as $file)
		{
			$name = substr($file, strlen(FORUM_ROOT));
			$lines = explode("\n", (string)file_get_contents($file));

			foreach ($lines as $number => $line)
			{
				// A read the line only tests for existence is not a read at all.
				$bare = preg_replace('/\b(?:isset|empty|array_key_exists)\s*\((?:[^()]|\([^()]*\))*\)/', '', $line);

				if (strpos((string)$bare, '$_COOKIE[') === false)
					continue;

				// The write-back in get_tracked_topics().
				if (preg_match('/^\s*\$_COOKIE\[[^\]]+\]\s*=[^=]/', $line))
					continue;

				if (strpos($line, 'is_string(') !== false)
					continue;

				$guarded = false;
				for ($back = max(0, $number - 3); $back < $number; $back++)
					if (strpos($lines[$back], 'is_string(') !== false && strpos($lines[$back], '$_COOKIE[') !== false)
						$guarded = true;

				if (!$guarded)
					$offenders[] = $name.':'.($number + 1).' '.trim($line);
			}
		}

		$this->assertSame(array(), $offenders, "unchecked cookie reads:\n".implode("\n", $offenders));
	}
}
