<?php
/**
 * PHPMailer carries what smtp_mail() and mail() carried: the board's address
 * as the sender, a UTF-8 subject and body, over the connection the relay was
 * configured for and nothing it was not.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/MailHarness.php';

class MailTransportTest extends TestCase
{
	/** @var array<string, mixed>|null */
	private static ?array $record = null;

	/** One plain send through the relay, shared by the cases that only read it. */
	private static function record(): array
	{
		if (self::$record === null)
		{
			[$output, $records] = MailHarness::relay('accept', array());
			self::$record = array('output' => $output) + ($records[0] ?? array());
		}

		return self::$record;
	}

	public function testTheMessageReachesTheRelay(): void
	{
		$record = self::record();

		$this->assertSame("RESULT=true\n", $record['output']);
		$this->assertSame('forum@example.com', $record['from']);
		$this->assertSame(array('someone@example.com'), $record['to']);
	}

	public function testTheHeadersNameTheBoard(): void
	{
		$data = self::record()['data'];

		$this->assertSame('Harness board Mailer <forum@example.com>', smtp_relay_header($data, 'From'));
		$this->assertSame('PunBB Mailer', smtp_relay_header($data, 'X-Mailer'));
		$this->assertSame('text/plain; charset=UTF-8', smtp_relay_header($data, 'Content-Type'));
		$this->assertSame('Subject Ümlaut', mb_decode_mimeheader(smtp_relay_header($data, 'Subject')));
	}

	/** A line starting with a dot is stuffed on the wire and arrives whole. */
	public function testTheBodyArrivesWhole(): void
	{
		$this->assertSame("Line one\r\n.Line two", rtrim(smtp_relay_body(self::record()['data'])));
	}

	/** Neither the old client nor this one sends STARTTLS: a relay that offers it is not taken up on it. */
	public function testAnOfferedStarttlsIsNotTaken(): void
	{
		$commands = self::record()['commands'];

		$this->assertContains('DATA', $commands);
		$this->assertNotContains('STARTTLS', $commands);
		$this->assertNotContains('AUTH', $commands, 'no credentials are configured');
	}

	public function testConfiguredCredentialsArePresented(): void
	{
		[$output, $records] = MailHarness::relay('accept', array('smtp_user' => 'relay-user', 'smtp_pass' => 'relay-pass'));

		$this->assertSame("RESULT=true\n", $output);
		$this->assertContains('AUTH', $records[0]['commands']);
		$this->assertSame('relay-user', $records[0]['user']);
	}

	/** mail() is handed the same message when no relay is configured. */
	public function testMailCarriesTheMessageWithoutARelay(): void
	{
		$file = (string) tempnam(sys_get_temp_dir(), 'sendmail');

		try
		{
			$this->assertSame("RESULT=true\n", MailHarness::send(array(), 'cat > '.escapeshellarg($file)));

			$message = str_replace("\r\n", "\n", (string) file_get_contents($file));
			$this->assertStringContainsString("To: someone@example.com\n", $message);
			$this->assertStringContainsString("From: Harness board Mailer <forum@example.com>\n", $message);
			$this->assertStringEndsWith("\n\nLine one\n.Line two", rtrim($message));
		}
		finally
		{
			unlink($file);
		}
	}
}
