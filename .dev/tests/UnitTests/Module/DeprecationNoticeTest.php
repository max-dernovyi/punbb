<?php
/**
 * A deprecation fires always, reaches the log and never a page: without
 * FORUM_DEBUG each notice is logged once per request, under FORUM_DEBUG once
 * per call site. Driven through the real boot of a scratch forum with the
 * fixture extension's code in the hooks cache.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

require_once FORUM_ROOT.'.dev/tests/UnitTests/Extensions/ScratchForum.php';

class DeprecationNoticeTest extends TestCase {
	private const HOOK_NOTICE = 'Running extension code at hook point %s through eval($hook) is deprecated since 2.0, use the event or the plugged contract method that replaces the point';

	private const RUN_NOTICE = 'Method PunBB\\Module\\LegacyBridge\\Hook\\StatementHookRunner::run() is deprecated since 2.0, use the event or the plugged contract method that replaces the point';

	private const HARNESS = 'deprecation_notice_harness.php';

	private static ?ScratchForum $forum = null;

	private static string $log = '';

	public static function setUpBeforeClass(): void {
		if (!class_exists('SQLite3'))
			return;

		self::$forum = new ScratchForum();
		self::$forum->addExtension('punbb_fixture');
		self::$forum->submit('admin/extensions.php', array('install' => 'punbb_fixture'), array('install_comply' => '1'));
		self::$forum->addScript(__DIR__.'/'.self::HARNESS);

		self::$log = (string) tempnam(sys_get_temp_dir(), 'punbb_deprecation_');
		self::$forum->logTo(self::$log);
	}

	public static function tearDownAfterClass(): void {
		self::$forum?->remove();
		self::$forum = null;

		if (self::$log !== '')
			unlink(self::$log);
	}

	/** @return array{string, list<string>} what the request printed, and each line it logged */
	private function request(string $script, bool $debug): array {
		if (self::$forum === null)
			$this->markTestSkipped('the scratch forum needs ext-sqlite3');

		self::$forum->debug($debug);
		file_put_contents(self::$log, '');

		$page = self::$forum->request($script);

		// A log file prefixes each line with its timestamp.
		$lines = preg_replace('/^\[[^\]]+\] /', '', (array) file(self::$log, FILE_IGNORE_NEW_LINES));

		return array($page, array_values(array_filter($lines, fn (string $line): bool => $line !== '')));
	}

	/** The log line for $notice reached at the site in $file carrying $marker. */
	private static function logged(string $notice, string $file, string $marker): string {
		foreach ((array) file(FORUM_ROOT.$file, FILE_IGNORE_NEW_LINES) as $number => $line)
			if (str_contains($line, $marker))
				return 'PunBB deprecation: '.$notice.' in '.realpath(FORUM_ROOT.$file).' on line '.($number + 1);

		throw new LogicException($marker.' is not in '.$file);
	}

	private function assertNothingOnThePage(string $page): void {
		$this->assertDoesNotMatchRegularExpression('/deprecat/i', $page);
	}

	/** Every logged line is the forum's own: nothing went through PHP's display or log. */
	private function assertOnlyNotices(array $lines): void {
		$this->assertNotEmpty($lines);
		$this->assertSame(array(), preg_grep('/^PunBB deprecation: /', $lines, PREG_GREP_INVERT));
		$this->assertSame(array_values(array_unique($lines)), $lines, 'a notice was logged twice');
	}

	public function testAVisitorsPageCarriesNoNoticeAndTheLogNamesEachPointItReached(): void {
		foreach (array(false, true) as $debug)
		{
			[$page, $lines] = $this->request('index.php', $debug);

			$this->assertStringContainsString('<p id="punbb-fixture-banner">', $page, 'the extension\'s code did not run');
			$this->assertNothingOnThePage($page);
			$this->assertOnlyNotices($lines);
			$this->assertContains(self::logged(sprintf(self::HOOK_NOTICE, 'in_qr_get_cats_and_forums'), 'index.php', "get_hook('in_qr_get_cats_and_forums')"), $lines);
			$this->assertContains(self::logged(sprintf(self::HOOK_NOTICE, 'in_main_output_start'), 'index.php', "get_hook('in_main_output_start')"), $lines);
		}
	}

	public function testWithoutForumDebugEachNoticeIsLoggedOnceAtItsFirstCallSite(): void {
		[$page, $lines] = $this->request(self::HARNESS, false);

		$this->assertStringContainsString('<p id="harness-page">page</p>', $page);
		$this->assertNothingOnThePage($page);
		$this->assertOnlyNotices($lines);

		$hook = sprintf(self::HOOK_NOTICE, 'vt_modify_topic_info');
		$harness = '.dev/tests/UnitTests/Module/'.self::HARNESS;

		$this->assertSame(array(self::logged($hook, $harness, '// hook: first')), array_values(preg_grep('/'.preg_quote($hook, '/').'/', $lines)));
		$this->assertSame(array(self::logged(self::RUN_NOTICE, $harness, '// run: first')), array_values(preg_grep('/StatementHookRunner::run\(\)/', $lines)));
	}

	public function testUnderForumDebugEveryCallSiteIsLoggedAndThePageStaysClean(): void {
		[$page, $lines] = $this->request(self::HARNESS, true);

		$this->assertStringContainsString('<p id="harness-page">page</p>', $page);
		$this->assertNothingOnThePage($page);
		$this->assertOnlyNotices($lines);

		$hook = sprintf(self::HOOK_NOTICE, 'vt_modify_topic_info');
		$harness = '.dev/tests/UnitTests/Module/'.self::HARNESS;

		$this->assertSame(array(
			self::logged($hook, $harness, '// hook: first'),
			self::logged($hook, $harness, '// hook: second'),
			self::logged($hook, 'include/PunBB/Module/LegacyBridge/Module.php', '\\get_hook($point)'),
		), array_values(preg_grep('/'.preg_quote($hook, '/').'/', $lines)));

		$this->assertSame(array(
			self::logged(self::RUN_NOTICE, $harness, '// run: first'),
			self::logged(self::RUN_NOTICE, $harness, '// run: second'),
		), array_values(preg_grep('/StatementHookRunner::run\(\)/', $lines)));
	}
}
