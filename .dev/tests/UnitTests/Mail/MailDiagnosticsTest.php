<?php
/**
 * What a failed send tells the visitor who triggered it.
 *
 * Registration and a password-reset request send mail on behalf of an
 * unauthenticated visitor, so a failure rendered as a page is public. The host,
 * the port and the server's own responses belong behind FORUM_DEBUG, and
 * nothing that comes off the socket is HTML. Every case sends through the real
 * forum_mail() to a relay that fails in one of the ways a relay can.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/MailHarness.php';

class MailDiagnosticsTest extends TestCase
{
	private const GENERIC = 'Unable to send e-mail.<br />Please contact the forum administrator.';

	/** @var array<string, string> */
	private static array $output = array();

	/** What forum_mail() printed when the relay failed as $failure. */
	private static function failed(string $failure, bool $quiet, bool $debug): string
	{
		$key = $failure.'/'.(int) $quiet.'/'.(int) $debug;

		if (!isset(self::$output[$key]))
		{
			$options = array('quiet' => $quiet, 'debug' => $debug);

			self::$output[$key] = ($failure === 'closed')
				? MailHarness::send($options + array('smtp_host' => '127.0.0.1:'.MailHarness::closedPort()))
				: MailHarness::relay($failure, $options)[0];
		}

		return self::$output[$key];
	}

	/** The three pre-fix messages, each of which reached the browser unconditionally. */
	public static function preFixMessages(): array
	{
		return array(
			'smtp host'			=> array("error('Could not connect to smtp host \"'.\$forum_config['o_smtp_host']"),
			'server response'	=> array("reported by the SMTP server: \"'.\$server_response"),
			'response codes'	=> array("error(\$expected_response.' Couldn\\'t get mail server response codes."),
		);
	}

	#[DataProvider('preFixMessages')]
	public function testThePreFixMessageIsGone(string $fragment): void
	{
		$this->assertStringNotContainsString($fragment, (string) file_get_contents(FORUM_ROOT.'include/email.php'));
	}

	/** The SMTP conversation is the library's: no socket of our own is left to leak through. */
	public function testTheHandRolledClientIsGone(): void
	{
		require_once FORUM_ROOT.'include/email.php';

		$this->assertFalse(function_exists('smtp_mail'));
		$this->assertFalse(function_exists('server_parse'));
		$this->assertFalse(class_exists('ForumMailException', false));
		$this->assertStringNotContainsString('fsockopen', (string) file_get_contents(FORUM_ROOT.'include/email.php'));
	}

	/** A relay that turns the connection away, one that refuses every recipient, and none at all. */
	public static function failures(): array
	{
		return array(
			'refused greeting'		=> array('refuse'),
			'rejected recipients'	=> array('reject'),
			'closed port'			=> array('closed'),
		);
	}

	#[DataProvider('failures')]
	public function testALoudFailureRendersTheGenericSentenceAlone(string $failure): void
	{
		$page = self::failed($failure, false, false);

		$this->assertStringContainsString('<p>'.self::GENERIC.'</p>', $page);
		$this->assertStringNotContainsString('127.0.0.1', $page);
		$this->assertStringNotContainsString('SMTP', $page);
		$this->assertStringNotContainsString('RESULT=', $page, 'error() ends the request');
	}

	/**
	 * A caller that has to answer the same whether the address is registered or
	 * not gets false instead of the error page: rendering one for the matched
	 * address alone is the account oracle the generic message closes. Nothing
	 * else is printed either, not under FORUM_DEBUG and not as a PHP warning:
	 * with display_errors on, a warning is as much of a tell as the page.
	 */
	#[DataProvider('failures')]
	public function testAQuietCallerGetsFalseAndNothingElse(string $failure): void
	{
		$this->assertSame("RESULT=false\n", self::failed($failure, true, true));
	}

	#[DataProvider('failures')]
	public function testTheDetailIsBehindTheDebugConstant(string $failure): void
	{
		$page = self::failed($failure, false, true);

		$this->assertStringContainsString('<p>'.self::GENERIC.' The SMTP server "127.0.0.1:', $page);
		$this->assertStringNotContainsString('Warning:', $page);
	}

	/** error() echoes its message raw, so what the server said has to be encoded. */
	public function testTheServersAnswerIsEncoded(): void
	{
		$page = self::failed('reject', false, true);

		$this->assertStringContainsString(forum_htmlencode(substr(SMTP_RELAY_REJECTION, 4)), $page);
		$this->assertStringNotContainsString('<i>no</i>', $page);
	}

	public function testTheConfiguredHostIsEncoded(): void
	{
		$page = MailHarness::send(array('smtp_host' => '<i>relay</i>:25', 'debug' => true));

		$this->assertStringContainsString('"&lt;i&gt;relay&lt;/i&gt;:25"', $page);
		$this->assertStringNotContainsString('<i>relay</i>', $page);
	}

	/** mail() failing is the host's to report: false for every caller, no page and no warning. */
	public function testAFailingMailFunctionIsAValue(): void
	{
		$this->assertSame("RESULT=false\n", MailHarness::send(array('quiet' => true, 'debug' => true), '/nonexistent/sendmail'));
		$this->assertSame("RESULT=false\n", MailHarness::send(array('quiet' => false, 'debug' => true), '/nonexistent/sendmail'));
	}
}
