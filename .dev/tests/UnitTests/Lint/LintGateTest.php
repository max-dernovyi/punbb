<?php
/**
 * The lint gate must fail on compile-time diagnostics, not only syntax errors.
 *
 * `php -l` reports things like "'continue' targeting switch is equivalent to
 * 'break'" and still exits 0, so parallel-lint alone marks such a file clean.
 * .dev/bin/lint.php adds the second pass; these tests pin that it goes red on a
 * file carrying such a diagnostic and green on an equivalent clean one.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class LintGateTest extends TestCase {
	private string $scratch = '';

	protected function tearDown(): void {
		if ($this->scratch !== '' && is_file($this->scratch))
			unlink($this->scratch);

		$this->scratch = '';
	}

	/**
	 * Copies a fixture to a .php file outside the repository (the gate walks
	 * the whole tree, so a fixture living in it would fail every other run).
	 *
	 * @return array{0: int, 1: string} exit status and combined output
	 */
	private function lint(string $fixture): array {
		$this->scratch = sys_get_temp_dir().'/lint_gate_'.getmypid().'.php';
		copy(__DIR__.'/fixtures/'.$fixture, $this->scratch);

		$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(FORUM_ROOT.'.dev/bin/lint.php').' '.escapeshellarg($this->scratch).' 2>&1';

		$output = array();
		$status = 0;
		exec($command, $output, $status);

		return array($status, implode("\n", $output));
	}

	public function testGateFailsOnAContinueTargetingSwitch(): void {
		list($status, $output) = $this->lint('continue_in_switch.phpt.txt');

		$this->assertSame(1, $status, 'the gate must fail on a compile-time warning: '.$output);
		$this->assertStringContainsString('"continue" targeting switch', $output);
	}

	public function testGatePassesOnTheFixedEquivalent(): void {
		list($status, $output) = $this->lint('clean.phpt.txt');

		$this->assertSame(0, $status, 'the gate must pass on a clean file: '.$output);
	}

	public function testFunctionsPhpCompilesWithoutDiagnostics(): void {
		$command = escapeshellarg(PHP_BINARY).' -d log_errors=0 -d display_errors=1 -l '.escapeshellarg(FORUM_ROOT.'include/functions.php').' 2>&1';

		$output = array();
		exec($command, $output);
		$output = implode("\n", $output);

		$this->assertStringNotContainsString('Warning:', $output);
		$this->assertStringNotContainsString('Deprecated:', $output);
	}

	public function testComposerLintRunsTheWrapper(): void {
		$composer = json_decode(file_get_contents(FORUM_ROOT.'composer.json'), true);

		$this->assertStringContainsString('.dev/bin/lint.php', $composer['scripts']['lint']);
	}
}
