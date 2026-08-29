<?php
/**
 * Golden-output baseline for the BBCode parser.
 *
 * Every fixture is pushed through preparse_bbcode() + parse_message(),
 * preparse_bbcode() + parse_signature() and both do_clickable() branches, and
 * the result is compared byte for byte with a committed .golden file. The
 * baseline was captured from the parser before the create_function() → closure
 * conversion (plan 02 task 3); any diff after it is a regression.
 *
 * Regenerate with: UPDATE_GOLDEN=1 vendor/bin/phpunit --filter ParserGolden
 *
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParserGoldenTest extends TestCase
{
	private const INPUT_DIR = __DIR__.'/fixtures/input';
	private const GOLDEN_DIR = __DIR__.'/fixtures/golden';

	public static function fixtureProvider(): array
	{
		$fixtures = array();

		foreach (glob(self::INPUT_DIR.'/*.txt') as $path)
			$fixtures[basename($path, '.txt')] = array(basename($path, '.txt'));

		ksort($fixtures);

		return $fixtures;
	}

	#[DataProvider('fixtureProvider')]
	public function testGoldenOutput(string $name): void
	{
		$actual = self::render(file_get_contents(self::INPUT_DIR.'/'.$name.'.txt'));
		$golden = self::GOLDEN_DIR.'/'.$name.'.golden';

		if (getenv('UPDATE_GOLDEN') === '1')
		{
			file_put_contents($golden, $actual);
			$this->addToAssertionCount(1);
			return;
		}

		$this->assertFileExists($golden, 'missing golden file; run with UPDATE_GOLDEN=1');
		$this->assertSame(file_get_contents($golden), $actual);
	}

	//
	// Render one fixture through every parser entry point touched by the
	// create_function() call sites.
	//
	private static function render(string $source): string
	{
		// preparse_bbcode() reads it when it rejects quote/code/list in a signature.
		global $lang_profile;
		if (!isset($lang_profile))
			require FORUM_ROOT.'lang/English/profile.php';

		$message_errors = array();
		$message = parse_message(preparse_bbcode(forum_trim($source), $message_errors), '0');

		$signature_errors = array();
		$signature = parse_signature(preparse_bbcode(forum_trim($source), $signature_errors, true));

		return implode("\n", array(
			'===== message =====',
			$message,
			'===== message-errors =====',
			implode("\n", $message_errors),
			'===== signature =====',
			$signature,
			'===== signature-errors =====',
			implode("\n", $signature_errors),
			'===== clickable-unicode =====',
			do_clickable($source, true),
			'===== clickable-ascii =====',
			do_clickable($source, false),
			'',
		));
	}
}
