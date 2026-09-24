<?php
/**
 * What reaches an SMTP envelope and a mail header.
 *
 * forum_mail() is handed a comma-separated $to that no caller is required to
 * have validated, and the transport writes one "RCPT TO" per address it is
 * given. The contract here is that the envelope and the headers only ever see
 * an address is_valid_email() accepted, and that is_valid_email() no longer
 * accepts the characters an envelope or a header reads. The sending cases go
 * through the real forum_mail() to a relay, or to mail() with sendmail_path
 * pointed at a file.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/MailHarness.php';

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

	private const HOSTILE_LIST = 'a@example.com, not an address, Bcc: victim@evil.com, b@example.org';

	public function testTheRelayIsHandedOnlyValidatedAddresses(): void
	{
		[$output, $records] = MailHarness::relay('accept', array('to' => self::HOSTILE_LIST));

		$this->assertSame("RESULT=true\n", $output);
		$this->assertCount(1, $records);
		$this->assertSame(array('a@example.com', 'b@example.org'), $records[0]['to']);
		$this->assertSame('a@example.com, b@example.org', smtp_relay_header($records[0]['data'], 'To'));
		$this->assertStringNotContainsString('victim', $records[0]['data']);
	}

	public function testMailIsHandedOnlyValidatedAddresses(): void
	{
		$file = (string) tempnam(sys_get_temp_dir(), 'sendmail');

		try
		{
			$this->assertSame("RESULT=true\n", MailHarness::send(array('to' => self::HOSTILE_LIST), 'cat > '.escapeshellarg($file)));

			$message = str_replace("\r\n", "\n", (string) file_get_contents($file));
			$this->assertStringContainsString("To: a@example.com, b@example.org\n", $message);
			$this->assertStringNotContainsString('victim', $message);
		}
		finally
		{
			unlink($file);
		}
	}

	/** is_valid_email() is the one judge: the library's own validator would refuse this address. */
	public function testAnAddressTheForumAcceptsIsSent(): void
	{
		[$output, $records] = MailHarness::relay('accept', array('to' => '"one two"@example.com'));

		$this->assertSame("RESULT=true\n", $output);
		$this->assertSame(array('"one two"@example.com'), $records[0]['to']);
		$this->assertFalse(filter_var('"one two"@example.com', FILTER_VALIDATE_EMAIL));
	}

	public function testNothingIsSentWhenNoAddressValidates(): void
	{
		[$output, $records] = MailHarness::relay('accept', array('to' => 'Bcc: victim@evil.com'));

		$this->assertSame("RESULT=NULL\n", $output);
		$this->assertSame(array(), $records);
	}

	/**
	 * Extension code at em_fn_forum_mail_pre_send still sees $to and $headers.
	 * What it adds to $to is filtered again, and a header it appends is carried
	 * over unless it names a recipient: those would reach the envelope through
	 * a sendmail that reads them.
	 */
	public function testAPreSendHookCannotSmuggleARecipientIn(): void
	{
		[$output, $records] = MailHarness::relay('accept', array(
			'to' => 'a@example.com',
			'pre_send' => '$to .= ",not an address,Bcc: x@evil.com"; $headers .= "\r\nX-Extension: kept\r\nBcc: hidden@evil.com\r\nCc: copy@evil.com";',
		));

		$this->assertSame("RESULT=true\n", $output);
		$this->assertSame(array('a@example.com'), $records[0]['to']);
		$this->assertSame('kept', smtp_relay_header($records[0]['data'], 'X-Extension'));
		$this->assertStringNotContainsString('evil.com', $records[0]['data']);
	}

	/** A hook that rewrites a header the library writes is still heard: HTML mail and a custom sender keep working. */
	public function testAPreSendHookRewritesContentTypeAndSender(): void
	{
		[$output, $records] = MailHarness::relay('accept', array(
			'to' => 'a@example.com',
			'reply_to' => 'reply@example.net',
			'pre_send' => '$headers = str_replace(array("Content-type: text/plain; charset=utf-8", "From: ".$from, "Reply-To: ".$reply_to), array("Content-type: text/html; charset=utf-8", "From: Ext <ext@example.org>", "Reply-To: other@example.org"), $headers);',
		));

		$this->assertSame("RESULT=true\n", $output);
		$this->assertSame('text/html; charset=utf-8', strtolower((string) smtp_relay_header($records[0]['data'], 'Content-Type')));
		$this->assertSame('Ext <ext@example.org>', smtp_relay_header($records[0]['data'], 'From'));
		$this->assertSame('forum@example.com', $records[0]['from']);
		$this->assertSame('other@example.org', smtp_relay_header($records[0]['data'], 'Reply-To'));
		$this->assertSame(1, substr_count(strtolower($records[0]['data']), "\r\nreply-to:"));
	}

	public function testAPreSendHookCannotSetAnInvalidSender(): void
	{
		[$output, $records] = MailHarness::relay('accept', array(
			'to' => 'a@example.com',
			'pre_send' => '$headers = str_replace("From: ".$from, "From: not an address", $headers);',
		));

		$this->assertSame("RESULT=true\n", $output);
		$this->assertStringNotContainsString('not an address', $records[0]['data']);
	}

	/** sendmail -t, PHP's default, takes its recipients from Resent-To/Cc/Bcc when any is present; the other Resent-* fields name no recipient. */
	public function testAPreSendHookCannotAddAResentRecipientForSendmail(): void
	{
		$file = (string) tempnam(sys_get_temp_dir(), 'sendmail');

		try
		{
			$output = MailHarness::send(array(
				'to' => 'a@example.com',
				'pre_send' => '$headers .= "\r\nX-Extension: kept\r\nResent-From: resender@example.com\r\nResent-To: to@evil.com\r\nresent-cc: cc@evil.com\r\nResent-Bcc:\r\n\tbcc@evil.com";',
			), 'cat > '.escapeshellarg($file));

			$message = str_replace("\r\n", "\n", (string) file_get_contents($file));
			$this->assertSame("RESULT=true\n", $output);
			$this->assertStringContainsString("X-Extension: kept\n", $message);
			$this->assertStringContainsString("Resent-From: resender@example.com\n", $message);
			$this->assertStringNotContainsStringIgnoringCase('resent-to', $message);
			$this->assertStringNotContainsStringIgnoringCase('resent-cc', $message);
			$this->assertStringNotContainsStringIgnoringCase('resent-bcc', $message);
			$this->assertStringNotContainsString('evil.com', $message);
		}
		finally
		{
			unlink($file);
		}
	}

	public function testAPreSendHookThatLeavesNoRecipientSuppressesTheMail(): void
	{
		[$output, $records] = MailHarness::relay('accept', array('to' => 'a@example.com', 'pre_send' => '$to = "not an address";'));

		$this->assertSame("RESULT=true\n", $output);
		$this->assertSame(array(), $records);
	}

	public function testAValidReplyToBecomesTheHeader(): void
	{
		[, $records] = MailHarness::relay('accept', array('reply_to' => 'reply@example.net'));

		$this->assertSame('Replier <reply@example.net>', smtp_relay_header($records[0]['data'], 'Reply-To'));
	}

	/** The spring cleaning strips the line break, and what is left is no address. */
	public function testAnInjectedReplyToIsDropped(): void
	{
		[$output, $records] = MailHarness::relay('accept', array('reply_to' => "reply@example.net\r\nBcc: victim@evil.com"));

		$this->assertSame("RESULT=true\n", $output);
		$this->assertSame('', smtp_relay_header($records[0]['data'], 'Reply-To'));
		$this->assertStringNotContainsString('victim', $records[0]['data']);
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
