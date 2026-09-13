<?php
/**
 * What the unauthenticated forms tell an attacker about who has an account.
 *
 * The password-reset form used to answer four different ways: no such address,
 * that address is an administrator's, that address was asked for a minute ago,
 * and a mail is on its way. Any one of them is an oracle for an address the
 * attacker only guessed. The login form answers the same for an unknown
 * username and for a wrong password; the reset form now answers that way too.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AccountEnumerationTest extends TestCase
{
	private static function login(): string
	{
		return (string) file_get_contents(FORUM_ROOT.'login.php');
	}

	/** The three answers that named the address, each removed with its string. */
	public static function oracleStrings(): array
	{
		return array(
			array('No e-mail match'),
			array('Email important'),
			array('Email flood'),
		);
	}

	#[DataProvider('oracleStrings')]
	public function testTheOracleIsNotEmittedByLogin(string $key): void
	{
		$this->assertStringNotContainsString("\$lang_login['".$key."']", self::login());
	}

	#[DataProvider('oracleStrings')]
	public function testTheOracleStringIsGoneFromTheLanguagePack(string $key): void
	{
		require FORUM_ROOT.'lang/English/login.php';

		$this->assertArrayNotHasKey($key, $lang_login, $key);
	}

	/** Both skips leave the loop rather than answering from inside it. */
	public function testTheAdministratorAndTheFloodCaseOnlySkipTheMail(): void
	{
		$source = self::login();

		$this->assertStringContainsString(
			"if (\$cur_hit['group_id'] == FORUM_ADMIN)\n\t\t\t\t\t\tcontinue;",
			$source
		);
		$this->assertStringContainsString(
			"(time() - \$cur_hit['last_email_sent']) >= 0)\n\t\t\t\t\t\tcontinue;",
			$source
		);
	}

	/**
	 * The one answer is emitted after the queue call closes, so an address with
	 * no account reaches it too.
	 */
	public function testTheOneAnswerIsOutsideTheWorkThatFoundTheUsers(): void
	{
		$source = self::login();

		$found = strpos($source, 'forum_defer(function ()');
		$answer = strpos($source, "message(sprintf(\$lang_login['Forget mail']");

		$this->assertIsInt($found);
		$this->assertIsInt($answer);

		$depth = 0;
		$close = null;
		for ($i = $found; $i < strlen($source); ++$i)
		{
			if ($source[$i] === '{')
				++$depth;
			else if ($source[$i] === '}')
			{
				--$depth;
				if ($depth === 0)
				{
					$close = $i;
					break;
				}
			}
		}

		$this->assertIsInt($close);
		$this->assertGreaterThan($close, $answer);
	}

	/**
	 * Reading the template, writing the reset key and reaching the relay are
	 * costs only a resettable address pays. Inside the response they are a
	 * timing oracle - and, because the first request moves the address into the
	 * flood window, one the visitor can calibrate against the same address.
	 */
	public function testTheWorkThatDiffersRunsAfterTheResponse(): void
	{
		$source = self::login();

		$deferred = strpos($source, 'forum_defer(function ()');
		$this->assertIsInt($deferred, 'the reset work must be queued, not run inline');

		foreach (array('activate_password.tpl', "activate_key=\\'", 'forum_mail(') as $cur_work)
		{
			$this->assertGreaterThan(
				$deferred,
				strpos($source, $cur_work),
				$cur_work.' must sit inside the deferred work'
			);
		}
	}

	/** A relay that is down must not answer the form at all. */
	public function testTheResetMailIsSentQuietly(): void
	{
		$this->assertStringContainsString(
			"forum_mail(\$email, \$mail_subject, \$cur_mail_message, '', '', true)",
			self::login()
		);
	}

	/** The reworded string no longer claims a mail was sent. */
	public function testTheOneAnswerDoesNotAssertThatAMailWentOut(): void
	{
		require FORUM_ROOT.'lang/English/login.php';

		$this->assertStringStartsWith('If that email address belongs to an account', $lang_login['Forget mail']);
	}
}
