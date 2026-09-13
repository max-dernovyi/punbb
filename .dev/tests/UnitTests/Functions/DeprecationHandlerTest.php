<?php
/**
 * forum_log_deprecation(): which notices it takes, and the call site it logs.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class DeprecationHandlerTest extends TestCase {
	private string $log = '';

	private string $include = '';

	private string|false $previousLog = false;

	private int $previousReporting = 0;

	/**
	 * Installs the handler as essentials.php does. PHPUnit points error_log at its own
	 * capture file and lowers error_reporting() once a test starts, so both are set here.
	 */
	private function handle(): void {
		$this->log = (string) tempnam(sys_get_temp_dir(), 'punbb_handler_log_');
		$this->previousLog = ini_set('error_log', $this->log);
		$this->previousReporting = error_reporting(E_ALL);
		set_error_handler('forum_log_deprecation', E_USER_DEPRECATED);
	}

	protected function tearDown(): void {
		if ($this->log !== '')
		{
			restore_error_handler();
			error_reporting($this->previousReporting);
			ini_set('error_log', (string) $this->previousLog);
			unlink($this->log);
		}

		if ($this->include !== '')
			unlink($this->include);
	}

	/** @return list<string> */
	private function logged(): array {
		return preg_replace('/^\[[^\]]+\] /', '', (array) file($this->log, FILE_IGNORE_NEW_LINES));
	}

	private static function deprecatedFunction(string $notice): void {
		trigger_error($notice, E_USER_DEPRECATED);
	}

	public function testEssentialsInstallsTheHandlerForUserDeprecationsOnly(): void {
		$source = (string) file_get_contents(FORUM_ROOT.'include/essentials.php');

		$this->assertSame(1, preg_match_all('/set_error_handler\(/', $source));
		$this->assertStringContainsString("set_error_handler('forum_log_deprecation', E_USER_DEPRECATED);", $source);
	}

	public function testANoticeInsideAFunctionIsLoggedAtItsCaller(): void {
		$this->handle();
		$notice = 'handler probe '.__FUNCTION__;
		self::deprecatedFunction($notice); $line = __LINE__;

		$this->assertSame(array('PunBB deprecation: '.$notice.' in '.__FILE__.' on line '.$line), $this->logged());
	}

	public function testANoticeAtTheTopOfAnIncludedFileIsLoggedWhereItWasRaised(): void {
		$this->handle();
		$notice = 'handler probe '.__FUNCTION__;
		$this->include = (string) tempnam(sys_get_temp_dir(), 'punbb_handler_inc_');
		file_put_contents($this->include, "<?php\n\ntrigger_error(".var_export($notice, true).", E_USER_DEPRECATED);\n");

		require $this->include;

		$this->assertSame(array('PunBB deprecation: '.$notice.' in '.$this->include.' on line 3'), $this->logged());
	}

	public function testUnderForumDebugEachCallSiteIsLoggedOnce(): void {
		$this->handle();
		$notice = 'handler probe '.__FUNCTION__;
		$lines = array();
		for ($i = 0; $i < 2; $i++)
		{
			self::deprecatedFunction($notice); $lines[] = __LINE__;
		}
		self::deprecatedFunction($notice); $other = __LINE__;

		$this->assertTrue(defined('FORUM_DEBUG'));
		$this->assertSame(array(
			'PunBB deprecation: '.$notice.' in '.__FILE__.' on line '.$lines[0],
			'PunBB deprecation: '.$notice.' in '.__FILE__.' on line '.$other,
		), $this->logged());
	}

	public function testASilencedNoticeIsNotLogged(): void {
		$this->handle();
		@trigger_error('handler probe '.__FUNCTION__, E_USER_DEPRECATED);

		$this->assertSame(array(), $this->logged());
	}
}
