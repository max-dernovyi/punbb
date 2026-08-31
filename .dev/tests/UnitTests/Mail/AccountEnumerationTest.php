<?php
/**
 * What the unauthenticated forms tell an attacker about who has an account.
 *
 * The password-reset form used to answer four different ways: no such address,
 * that address is an administrator's, that address was asked for a minute ago,
 * and a mail is on its way. Any one of them is an oracle for an address the
 * attacker only guessed. The login form has always answered the same for an
 * unknown username and a wrong password; it now also takes the same time to.
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
	 * The one answer is emitted after the branch that found the users closes,
	 * so an address with no account reaches it too.
	 */
	public function testTheOneAnswerIsOutsideTheMatchBranch(): void
	{
		$source = self::login();

		$found = strpos($source, 'if (!empty($users_with_email))');
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

	/** The reworded string no longer claims a mail was sent. */
	public function testTheOneAnswerDoesNotAssertThatAMailWentOut(): void
	{
		require FORUM_ROOT.'lang/English/login.php';

		$this->assertStringStartsWith('If that email address belongs to an account', $lang_login['Forget mail']);
	}

	public function testTheDummyHashIsAPasswordHashNoPasswordMatches(): void
	{
		$this->assertTrue(forum_password_is_modern(FORUM_DUMMY_PASSWORD_HASH));
		$this->assertFalse(forum_password_verify('', FORUM_DUMMY_PASSWORD_HASH, ''));
		$this->assertFalse(forum_password_verify('password', FORUM_DUMMY_PASSWORD_HASH, ''));
	}

	/**
	 * The dummy only equalises the work if it costs what a real hash costs.
	 * A different algorithm, or a bcrypt cost the runtime no longer defaults
	 * to, turns the timing oracle back on - pointing the other way.
	 */
	public function testTheDummyHashCostsWhatARealOneCosts(): void
	{
		$dummy = password_get_info(FORUM_DUMMY_PASSWORD_HASH);
		$real = password_get_info(forum_password_hash('password'));

		$this->assertSame($real['algo'], $dummy['algo']);
		$this->assertSame($real['options'], $dummy['options']);
		$this->assertFalse(password_needs_rehash(FORUM_DUMMY_PASSWORD_HASH, PASSWORD_DEFAULT));
	}

	/** A username matching no row still pays for a verification. */
	public function testTheMissingUserPathStillVerifies(): void
	{
		$this->assertStringContainsString(
			"forum_password_verify(\$form_password, FORUM_DUMMY_PASSWORD_HASH, '');",
			self::login()
		);
	}

	/**
	 * A legacy sha1/md5 row verifies in microseconds. Without the dummy on that
	 * path too, the account that has not been rehashed yet is the fast answer
	 * and the timing oracle runs backwards.
	 */
	public function testALegacyHashPaysForTheSameBcrypt(): void
	{
		$salt = 'saltsaltsalt';
		$legacy = forum_hash('password', $salt);

		$this->assertTrue(forum_password_verify('password', $legacy, $salt));
		$this->assertFalse(forum_password_verify('wrong', $legacy, $salt));

		$source = (string) file_get_contents(FORUM_ROOT.'include/functions.php');
		$body = substr($source, (int) strpos($source, 'function forum_password_verify('));
		$body = substr($body, 0, (int) strpos($body, 'function forum_password_needs_rehash('));

		$this->assertStringContainsString('password_verify($password, FORUM_DUMMY_PASSWORD_HASH)', $body);
		$this->assertLessThan(
			(int) strpos($body, "strlen(\$stored_hash) == 40"),
			(int) strpos($body, 'password_verify($password, FORUM_DUMMY_PASSWORD_HASH)'),
			'the dummy has to be paid for before the legacy comparison returns'
		);
	}

	/**
	 * A relay that is down must not answer the reset form with an error page:
	 * that page is only reachable for an address that has an account.
	 */
	public function testTheResetMailIsSentQuietly(): void
	{
		$this->assertStringContainsString(
			"forum_mail(\$email, \$mail_subject, \$cur_mail_message, '', '', true);",
			self::login()
		);
	}

	/** The pre-fix shape: the verification only ever ran when a row came back. */
	public function testThePreFixShapeIsGone(): void
	{
		$this->assertStringNotContainsString(
			"\$authorized = false;\n\tif (!empty(\$db_password_hash))",
			self::login()
		);
	}
}
