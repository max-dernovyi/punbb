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
use PunBB\Module\Login\Model\Credentials;
use PunBB\Module\Login\Model\ResettableAccount;

require_once FORUM_ROOT.'.dev/tests/UnitTests/Page/LoginFakes.php';

class AccountEnumerationTest extends TestCase
{
	private static function controller(): string
	{
		return (string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Login/Controller/LoginController.php');
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
		$this->assertStringNotContainsString("'".$key."'", self::controller());
	}

	#[DataProvider('oracleStrings')]
	public function testTheOracleStringIsGoneFromTheLanguagePack(string $key): void
	{
		require FORUM_ROOT.'lang/English/login.php';

		$this->assertArrayNotHasKey($key, $lang_login, $key);
	}

	/**
	 * Every kind of address a visitor can guess: none, an administrator's, one
	 * asked for a minute ago, and one that gets a mail.
	 *
	 * @return array<string, array{list<ResettableAccount>, bool}>
	 */
	public static function addresses(): array
	{
		return array(
			'no account'			=> array(array(), false),
			'an administrator'		=> array(array(new ResettableAccount(2, 1, 'admin', null)), false),
			'asked a minute ago'	=> array(array(new ResettableAccount(5, 3, 'anna', time() - 60)), false),
			'a resettable account'	=> array(array(new ResettableAccount(5, 3, 'anna', null)), true),
		);
	}

	/**
	 * One answer for every address, and none of the work that tells them apart
	 * — reading the template, writing the key, reaching the relay — before the
	 * answer is delivered.
	 *
	 * @param list<ResettableAccount> $accounts
	 */
	#[DataProvider('addresses')]
	public function testTheOneAnswerComesBeforeTheWorkThatDiffers(array $accounts, bool $mailed): void
	{
		$reference = new LoginKit();
		$answer = $reference->page(array('action' => 'forget'), array('form_sent' => '1', 'req_email' => 'someone@example.com'));

		$login = new LoginKit();
		$login->services->resettable['someone@example.com'] = $accounts;
		$login->kit->language->mailTemplates = array();

		$this->assertSame($answer, $login->page(array('action' => 'forget'), array('form_sent' => '1', 'req_email' => 'someone@example.com')));
		$this->assertSame(array('resettable someone@example.com'), $login->services->log, 'nothing but the lookup runs before the answer');
		$this->assertCount(1, $login->services->deferred);

		$login->kit->language->mailTemplates['activate_password'] = "Subject: Reset\n\n<activation_url>";
		$login->services->runDeferred();

		$this->assertSame($mailed, $login->services->mail !== array());
		$this->assertSame($mailed, in_array('reset key 5 key8r', $login->services->log, true));
	}

	/** A relay that is down must not answer the reset form with an error page: that page is only reachable for an address that has an account. */
	public function testTheResetMailIsSentQuietly(): void
	{
		$login = new LoginKit();
		$login->services->resettable['anna@example.com'] = array(new ResettableAccount(5, 3, 'anna', null));

		$login->page(array('action' => 'forget'), array('form_sent' => '1', 'req_email' => 'anna@example.com'));
		$login->services->runDeferred();

		$this->assertTrue($login->services->mail[0][3]);
		$this->assertStringContainsString('\\forum_mail($to, $subject, $message, $replyTo, $replyToName, $quiet);',
			(string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/LegacyBridge/Site/LegacyMailer.php'));
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

	/** A username matching no row, or a row without a password, still pays for a verification against the dummy. */
	public function testTheMissingUserPathStillVerifies(): void
	{
		$login = new LoginKit();
		$login->services->credentials['none'] = new Credentials(7, 3, '', '');

		$login->page(array(), array('form_sent' => '1', 'req_username' => 'nobody', 'req_password' => 'x'));
		$login->page(array(), array('form_sent' => '1', 'req_username' => 'none', 'req_password' => 'x'));

		$this->assertSame(2, count(array_keys($login->services->log, 'verify against nobody', true)));
		$this->assertStringContainsString('\\forum_password_verify($password, \\FORUM_DUMMY_PASSWORD_HASH, \'\');',
			(string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/LegacyBridge/Site/LegacyPasswords.php'));
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

	/** The pre-fix shape: the verification only ever ran when a row came back. */
	public function testThePreFixShapeIsGone(): void
	{
		$this->assertStringContainsString("if (\$credentials === null || \$credentials->passwordHash() === '')\n\t\t\t\$this->passwords->verifyAgainstNobody(\$password);", self::controller());
	}
}
