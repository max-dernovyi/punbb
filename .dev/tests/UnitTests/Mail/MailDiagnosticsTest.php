<?php
/**
 * What an SMTP failure tells the visitor who triggered it.
 *
 * Registration and a password-reset request send mail on behalf of an
 * unauthenticated visitor, so every error() in the SMTP path renders on a
 * public page. The host, the port and the server's own responses belong behind
 * FORUM_DEBUG, and nothing that comes off the socket is HTML.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MailDiagnosticsTest extends TestCase
{
	private static function source(): string
	{
		return (string) file_get_contents(FORUM_ROOT.'include/email.php');
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
		$this->assertStringNotContainsString($fragment, self::source());
	}

	/** Every failure in this file says the same thing before it says anything else. */
	public function testEveryFailureStartsWithTheGenericSentence(): void
	{
		$source = self::source();

		preg_match_all('/\bthrow new ForumMailException\(([^\n]*)/', $source, $matches);

		$this->assertCount(3, $matches[1]);

		foreach ($matches[1] as $cur_call)
		{
			$this->assertStringStartsWith(
				"'Unable to send e-mail.<br />Please contact the forum administrator.'",
				$cur_call
			);
		}

		// The one place a failure becomes a page, and it renders what was thrown.
		preg_match_all('/\berror\(([^\n]*)/', $source, $matches);

		$this->assertCount(1, $matches[1]);
		$this->assertStringStartsWith('$e->getMessage()', $matches[1][0]);
	}

	/**
	 * A caller that has to answer the same whether the address is registered or
	 * not gets false instead of the error page: rendering one for the matched
	 * address alone is the account oracle the generic message closes.
	 */
	public function testAQuietCallerGetsNoErrorPage(): void
	{
		$source = self::source();

		$this->assertStringContainsString('$quiet = false', $source);
		$this->assertMatchesRegularExpression(
			'/catch \(ForumMailException \$e\)\s*\{.*?if \(\$quiet\)\s*return false;.*?error\(/s',
			$source
		);
	}

	/** Each of the three appends its detail only under FORUM_DEBUG. */
	public function testEveryDetailIsBehindTheDebugConstant(): void
	{
		$this->assertSame(3, substr_count(self::source(), "defined('FORUM_DEBUG')"));
	}

	public static function untrustedValues(): array
	{
		return array(
			array('$server_response'),
			array('$expected_response'),
			array("$"."forum_config['o_smtp_host']"),
			array('$errstr'),
		);
	}

	/** error() echoes its message raw, so a value off the socket has to be encoded. */
	#[DataProvider('untrustedValues')]
	public function testAnUntrustedValueIsEncoded(string $value): void
	{
		$this->assertStringContainsString('forum_htmlencode('.$value.')', self::source());
	}

	/**
	 * A warning off the transport is as much of a tell as the error page:
	 * with display_errors on it appears for the address that got a send and
	 * not for the one that did not.
	 */
	public function testTheTransportEmitsNoWarning(): void
	{
		$source = self::source();

		$this->assertStringContainsString('return (bool) @mail($to, $subject, $message, $headers);', $source);
		$this->assertSame(0, preg_match('/(?<!@)\bfwrite\(\$socket/', $source),
			'a socket write warns on a dead relay');
		$this->assertStringContainsString('@fgets($socket, 256)', $source);
	}

	/** The connect failure is reported by the function, not by a PHP warning naming the host. */
	public function testTheConnectIsSuppressed(): void
	{
		$this->assertStringContainsString('@fsockopen($smtp_host, $smtp_port', self::source());
	}
}
