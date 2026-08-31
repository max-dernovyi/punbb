<?php
/**
 * What reaches an SMTP envelope and a mail header.
 *
 * forum_mail() assembles every header by concatenation, and smtp_mail() writes
 * the envelope one "RCPT TO" line at a time from a comma-separated $to that no
 * caller is required to have validated. The contract here is that the split and
 * the headers only ever see an address is_valid_email() accepted, and that
 * is_valid_email() no longer accepts the characters those two read.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MailEnvelopeTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		// The bootstrap does not load it; nothing here may depend on another
		// test having reached it first.
		require_once FORUM_ROOT.'include/email.php';
	}

	private static function source(): string
	{
		return (string) file_get_contents(FORUM_ROOT.'include/email.php');
	}

	/** The pre-fix pattern, so a revert of the quoted branch fails here. */
	private const PRE_FIX_PATTERN = '/^(([^<>()[\]\\.,;:\s@"\']+(\.[^<>()[\]\\.,;:\s@"\']+)*)|("[^"\']+"))@((\[\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\])|(([a-zA-Z\d\-]+\.)+[a-zA-Z]{2,}))$/';

	public static function ordinaryAddresses(): array
	{
		return array(
			array('user@example.com'),
			array('first.last@sub.example.co.uk'),
			array('a-b_c+d@example.com'),
			array('user@[127.0.0.1]'),
			array('"one two"@example.com'),
		);
	}

	#[DataProvider('ordinaryAddresses')]
	public function testAnOrdinaryAddressIsStillValid(string $email): void
	{
		$this->assertSame(1, is_valid_email($email), $email);
	}

	/** Everything an envelope or a header would read, in the quoted local part. */
	public static function hostileAddresses(): array
	{
		return array(
			'recipient separator'	=> array('"a,b"@example.com'),
			'address delimiters'	=> array('"a>,<victim@evil.com"@example.com'),
			'header separator'		=> array('"a:b"@example.com'),
			'address list'			=> array('"a;b"@example.com'),
			'second at sign'		=> array('"a@evil.com"@example.com'),
			'backslash'				=> array('"a\\b"@example.com'),
			'carriage return'		=> array("\"a\rb\"@example.com"),
			'line feed'				=> array("\"a\nb\"@example.com"),
			'null byte'				=> array("\"a\0b\"@example.com"),
			'trailing newline'		=> array("user@example.com\n"),
			'trailing crlf'			=> array("user@example.com\r\n"),
		);
	}

	#[DataProvider('hostileAddresses')]
	public function testAHostileAddressIsRefused(string $email): void
	{
		$this->assertSame(0, is_valid_email($email), $email);
	}

	/**
	 * The control: these passed the pattern the fix replaced, so the cases are
	 * proof of a change and not of a rule that always held. The CRLF case is
	 * absent because /^...$/ without /D only ever forgave a single trailing
	 * newline, which is the case above it.
	 */
	public static function preFixAcceptedAddresses(): array
	{
		$cases = self::hostileAddresses();
		unset($cases['trailing crlf']);

		return $cases;
	}

	#[DataProvider('preFixAcceptedAddresses')]
	public function testThePreFixPatternAcceptedIt(string $email): void
	{
		$this->assertSame(1, preg_match(self::PRE_FIX_PATTERN, $email), $email);
	}

	public function testTheRecipientListKeepsOnlyValidatedAddresses(): void
	{
		$this->assertSame(
			array('a@example.com', 'b@example.org'),
			forum_mail_recipients('a@example.com, not an address, b@example.org, ')
		);
	}

	public function testTheRecipientListIsEmptyWhenNothingValidates(): void
	{
		$this->assertSame(array(), forum_mail_recipients('Bcc: victim@evil.com'));
		$this->assertSame(array(), forum_mail_recipients(''));
		$this->assertSame(array(), forum_mail_recipients(array('a@example.com')));
	}

	/** o_mailing_list is a comma-separated list, so the split has to survive. */
	public function testAMailingListSurvivesTheFilter(): void
	{
		$this->assertSame(
			array('one@example.com', 'two@example.com'),
			forum_mail_recipients("one@example.com,\ttwo@example.com")
		);
	}

	public function testSmtpMailFiltersTheEnvelopeItself(): void
	{
		$this->assertFalse(smtp_mail('Bcc: victim@evil.com', 'subject', 'message'));
	}

	public function testForumMailFiltersBeforeAnyHeaderIsBuilt(): void
	{
		$source = self::source();
		$envelope = strpos($source, '$recipients = forum_mail_recipients($to);');
		$headers = strpos($source, "\$headers = 'From: '");

		$this->assertIsInt($envelope);
		$this->assertIsInt($headers);
		$this->assertLessThan($headers, $envelope);
	}

	public function testTheReplyToAddressIsValidatedBeforeItBecomesAHeader(): void
	{
		$this->assertStringContainsString(
			"if (\$reply_to_email !== '' && !is_valid_email(\$reply_to_email))",
			self::source()
		);
	}

	/** The pre-fix split, which trusted $to whole. */
	public function testTheRawSplitIsGone(): void
	{
		$this->assertStringNotContainsString("explode(',', \$to)", self::source());
	}
}
