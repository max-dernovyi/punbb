<?php
/**
 * What a failed send is allowed to say.
 *
 * A relay that is down used to render the forum error page naming the host,
 * the port and the SMTP server's own reply - unescaped, and to whoever
 * submitted the form. On the password-reset path that page was also the tell:
 * only an address with a resettable account ever reached the relay.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class MailDiagnosticsTest extends TestCase
{
	private static function email(): string
	{
		return (string) file_get_contents(FORUM_ROOT.'include/email.php');
	}

	/** A send that fails raises; it does not decide what the page looks like. */
	public function testTheSmtpPathNeverCallsErrorItself(): void
	{
		$source = self::email();
		$smtp = substr($source, strpos($source, 'function server_parse('));

		$this->assertStringNotContainsString('error(', $smtp, 'smtp_mail() and server_parse() must throw');
		$this->assertStringContainsString('throw new ForumMailException(', $smtp);
	}

	/** forum_mail() is the only place that turns a failure into a page. */
	public function testOnlyForumMailDecidesWhetherToRenderTheErrorPage(): void
	{
		$this->assertSame(1, substr_count(self::email(), 'error($e->getMessage(), __FILE__, __LINE__);'));
	}

	/** The caller that must answer alike for every address gets a return value. */
	public function testAQuietCallerIsNeverAnsweredWithAnErrorPage(): void
	{
		$source = self::email();

		$this->assertStringContainsString('$quiet = false)', $source);
		$this->assertStringContainsString("if (\$quiet)\n\t\t\t\treturn false;", $source);
		$this->assertStringContainsString('return (bool) @mail(', $source, 'mail() warns, and a quiet caller must not print it');
	}

	/** The relay, its port and its reply are diagnostics, not visitor-facing text. */
	public function testServerDiagnosticsAreGatedOnDebug(): void
	{
		$source = self::email();

		foreach (array("o_smtp_host", '$errno', '$errstr', '$server_response') as $cur_diagnostic)
		{
			foreach (self::linesMentioning($source, $cur_diagnostic) as $cur_line)
			{
				if (strpos($cur_line, 'ForumMailException') === false)
					continue;

				$this->assertStringContainsString("defined('FORUM_DEBUG')", $cur_line, $cur_diagnostic);
			}
		}
	}

	/** An SMTP reply is remote input and reaches the page as text, not markup. */
	public function testRemoteInputIsEncodedBeforeItReachesThePage(): void
	{
		$source = self::email();

		foreach (self::linesMentioning($source, 'ForumMailException') as $cur_line)
		{
			foreach (array('$server_response', '$errstr', '$errno', "\$forum_config['o_smtp_host']") as $cur_value)
			{
				if (strpos($cur_line, $cur_value) === false)
					continue;

				$this->assertStringContainsString('forum_htmlencode('.$cur_value, str_replace('(string) ', '', $cur_line), $cur_value);
			}
		}
	}

	/** @return string[] */
	private static function linesMentioning(string $source, string $needle): array
	{
		$lines = array();

		foreach (explode("\n", $source) as $cur_line)
		{
			if (strpos($cur_line, $needle) !== false)
				$lines[] = $cur_line;
		}

		return $lines;
	}
}
