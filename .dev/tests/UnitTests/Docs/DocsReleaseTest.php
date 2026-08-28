<?php
/**
 * Pins the documentation this migration leaves behind: README.md is the single
 * user-facing document, the plain-text README only points at it, the ChangeLog
 * carries every breaking change of the series, and CLAUDE.md names the gates
 * that actually exist.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocsReleaseTest extends TestCase
{
	private function doc(string $name): string
	{
		$path = FORUM_ROOT.$name;
		$this->assertFileExists($path);

		return file_get_contents($path);
	}

	/** The 1.5.0-php84 entry, up to the previous release heading. */
	private function currentChangeLogEntry(): string
	{
		$changelog = $this->doc('ChangeLog');
		$next = strpos($changelog, 'PunBB 1.4.4');
		$this->assertNotFalse($next, 'the ChangeLog no longer has a 1.4.4 entry to bound the current one');

		return substr($changelog, 0, $next);
	}

	public function testPlainReadmePointsAtTheMarkdownOne(): void
	{
		$readme = $this->doc('README');

		$this->assertStringContainsString('README.md', $readme);
		$this->assertStringContainsString('ChangeLog', $readme);
		$this->assertStringContainsString('COPYING', $readme);
	}

	/**
	 * Two documents saying the same thing drift. The plain README was folded
	 * into README.md and must not grow its own copy of the instructions back.
	 */
	public function testPlainReadmeDoesNotDuplicateTheInstructions(): void
	{
		$readme = $this->doc('README');

		foreach (array('install.php', 'db_update.php', 'composer install', 'Requirements') as $duplicated)
			$this->assertStringNotContainsString($duplicated, $readme, 'README repeats "'.$duplicated.'" instead of pointing at README.md');
	}

	/** The PHP 5 era is over: no advice that only made sense back then. */
	public function testPlainReadmeCarriesNoStaleAdvice(): void
	{
		$this->assertDoesNotMatchRegularExpression('/Zeus|register_globals|magic quotes|777/i', $this->doc('README'));
	}

	public function testReadmeDocumentsTheUpgradePath(): void
	{
		$readme = $this->doc('README.md');

		foreach (array('## Upgrade', 'db_update.php', 'composer install --no-dev', 'cache/') as $step)
			$this->assertStringContainsString($step, $readme, 'the upgrade section does not mention '.$step);
	}

	public function testReadmeDocumentsTheComposerBootstrap(): void
	{
		$readme = $this->doc('README.md');

		$this->assertStringContainsString('composer install --no-dev', $readme);
		$this->assertStringContainsString('vendor/autoload.php', $readme);
		$this->assertStringContainsString('.htaccess.dist', $readme);
	}

	/** README.md is the product's document; the dev environment is not shipped. */
	public function testReadmeCarriesNothingAboutTheLocalEnvironment(): void
	{
		$this->assertDoesNotMatchRegularExpression(
			'/docker|docker-compose|punbb\.loc|make check|phpunit|phpstan/i',
			$this->doc('README.md'),
			'README.md describes the local development environment'
		);
	}

	/** @return array<string, array{string}> */
	public static function breakingChanges(): array
	{
		return array(
			'dropped drivers'   => array('mysqli_innodb'),
			'PHP 8.4 minimum'   => array('PHP 8.4 is now the minimum supported version'),
			'Composer'          => array('vendor/autoload.php'),
			'IDNA'              => array('IDNA2003'),
			'LABjs'             => array('LABjs'),
			'asset build'       => array('Dropped the asset build'),
			'minified assets'   => array('Oxygen.min.css'),
		);
	}

	#[DataProvider('breakingChanges')]
	public function testChangeLogRecordsEveryBreakingChange(string $marker): void
	{
		$this->assertStringContainsString($marker, $this->currentChangeLogEntry());
	}

	/**
	 * The minified asset paths are gone: the entry must say so, not point an
	 * administrator at a directory the release no longer ships.
	 */
	public function testChangeLogDoesNotPointAtTheRemovedAssetPaths(): void
	{
		$entry = $this->currentChangeLogEntry();

		$this->assertStringNotContainsString('include/js/min/', $entry);
		$this->assertStringContainsString('include/js/*.js', $entry);
	}

	public function testClaudeMdDocumentsBothGates(): void
	{
		$claude = $this->doc('CLAUDE.md');

		$this->assertStringContainsString('make -C $ENV check', $claude);
		$this->assertStringContainsString('check-full', $claude);

		foreach (array('test-install', 'test-upgrade', 'test-flows') as $target)
			$this->assertStringContainsString($target, $claude, $target.' is not documented in CLAUDE.md');
	}

	public function testClaudeMdCountsTheFrozenUtf8ApiCorrectly(): void
	{
		$implemented = preg_match_all('/^function (utf8_\w+)/m', $this->doc('include/utf8.php'));

		$this->assertStringContainsString('the '.$implemented.' `utf8_*` functions', $this->doc('CLAUDE.md'));
	}

	/**
	 * The migration record lives in the working tree, not in the repository
	 * history, so a checkout without it must not fail the build.
	 */
	public function testTheCompletedPlansAreKept(): void
	{
		if (!is_dir(FORUM_ROOT.'docs/plans'))
			$this->markTestSkipped('this checkout carries no docs/plans');

		$this->assertNotEmpty(glob(FORUM_ROOT.'docs/plans/completed/*.md'), 'docs/plans/completed/ holds no plan');
		$this->assertFileExists(FORUM_ROOT.'docs/plans/README.md');
	}
}
