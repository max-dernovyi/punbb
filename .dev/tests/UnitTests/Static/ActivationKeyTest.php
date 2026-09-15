<?php
/**
 * The mailed activate_key authorises a password change on a GET request, with
 * no session and no password behind it. Two things were wrong with how it was
 * checked, and both need a live forum to reach, so they are pinned here.
 *
 * 1. The comparison was `$key != $user['activate_key']`. PHP 8 still compares
 *    two numeric strings as numbers, and random_key(8, true) draws from
 *    [A-Za-z0-9] — a key like `0e123456` is a numeric string worth 0, so the
 *    single character `0` matches it.
 * 2. Nothing expired the key. It stayed usable from the moment the mail was
 *    sent until somebody used it, which on an abandoned mailbox is forever.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActivationKeyTest extends TestCase
{
	//
	// Keys random_key(8, true) can produce, together with the value that
	// matched them under the loose comparison without being them.
	//
	public static function collidingKeys(): array
	{
		return array(
			'zero exponent'		 => array('0e123456', '0'),
			'padded exponent'	 => array('00e12345', '0.0'),
			'decimal key'		 => array('12345678', ' 12345678'),
		);
	}

	#[DataProvider('collidingKeys')]
	public function testTheLooseComparisonAcceptedAKeyThatWasNotTheKey(string $stored, string $supplied): void
	{
		// The comparison the two branches performed, verbatim.
		$this->assertFalse($stored != $supplied,
			'this row no longer describes a PHP 8 numeric-string collision');

		$this->assertFalse(hash_equals($stored, $supplied),
			'hash_equals() must reject a value that is not the key');
	}

	#[DataProvider('collidingKeys')]
	public function testTheCollidingKeysAreKeysTheGeneratorCanProduce(string $stored, string $supplied): void
	{
		$this->assertMatchesRegularExpression('/\A[A-Za-z0-9]{8}\z/', $stored);
	}

	public function testHashEqualsStillAcceptsTheRealKey(): void
	{
		$key = random_key(8, true);

		$this->assertTrue(hash_equals($key, $key));
	}

	//
	// Both branches that check a mailed key, each in its controller.
	//
	public static function keyChecks(): array
	{
		return array(
			'password reset' => array('include/PunBB/Module/Profile/Controller/PasswordChange.php', '\'Pass key bad\''),
			'e-mail change'	 => array('include/PunBB/Module/Profile/Controller/EmailChange.php', '\'E-mail key bad\''),
		);
	}

	/** @return string the source of the branch that ends refusing the key with $message */
	private static function branch(string $file, string $message): string
	{
		$source = (string) file_get_contents(FORUM_ROOT.$file);
		$branch = substr($source, 0, (int) strpos($source, $message));

		return substr($branch, (int) strrpos($branch, '$key = '));
	}

	#[DataProvider('keyChecks')]
	public function testTheBranchComparesInConstantTime(string $file, string $message): void
	{
		$branch = self::branch($file, $message);

		$this->assertStringContainsString('!hash_equals($user->activateKey(), $key)', $branch);
		$this->assertDoesNotMatchRegularExpression('#\$key\s*!==?\s*\$user->activateKey\(\)|activateKey\(\)\s*!==?\s*\$key#', $branch,
			'the branch is back on a plain comparison');
	}

	#[DataProvider('keyChecks')]
	public function testTheKeyIsReadAsAString(string $file, string $message): void
	{
		// hash_equals() raises a TypeError on ?key[]=, which the loose
		// comparison merely returned true for.
		$this->assertStringContainsString('$key = is_string($request->query[\'key\']) ? $request->query[\'key\'] : \'\';', self::branch($file, $message));
	}

	//
	// The reset key expires; the registration key, which has no mail timestamp
	// behind it, is left to the three-day prune in register.php.
	//
	public function testTheResetKeyExpires(): void
	{
		$branch = self::branch('include/PunBB/Module/Profile/Controller/PasswordChange.php', '\'Pass key bad\'');

		$this->assertStringContainsString('|| $expired)', $branch);
		$this->assertStringContainsString('FORUM_PASSWORD_RESET_TTL',
			(string) file_get_contents(FORUM_ROOT.'include/functions.php'));
	}

	public function testTheWindowMatchesTheOneTheResendIsRefusedFor(): void
	{
		$this->assertSame(3600, FORUM_PASSWORD_RESET_TTL);
		$this->assertStringContainsString('return (int) Markers::markup(\\FORUM_PASSWORD_RESET_TTL);',
			(string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/LegacyBridge/Site/LegacyPasswords.php'),
			'a key that expired could not be replaced immediately');
		$this->assertStringContainsString('$this->passwords->resetKeyLifetime()',
			(string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Login/Controller/LoginController.php'),
			'the resend window is no longer the key\'s lifetime');
	}

	//
	// The condition itself, on the two cases that decide it: a key issued now
	// and one issued before the window.
	//
	public static function issueTimes(): array
	{
		return array(
			'just issued'		=> array(0, false),
			'inside the window'	=> array(FORUM_PASSWORD_RESET_TTL - 60, false),
			'on the boundary'	=> array(FORUM_PASSWORD_RESET_TTL, true),
			'long expired'		=> array(FORUM_PASSWORD_RESET_TTL * 24, true),
			'no mail behind it'	=> array(null, false),
		);
	}

	#[DataProvider('issueTimes')]
	public function testTheExpiryCondition(?int $age, bool $expected): void
	{
		$last_email_sent = ($age === null) ? '' : (string) (time() - $age);

		$this->assertSame($expected, forum_reset_key_expired($last_email_sent));
	}

	/**
	 * The profile decides as the function does, over the lifetime the site
	 * reports: a key mailed at a known time expires once the window has passed.
	 */
	public function testTheProfileDecidesAsTheFunction(): void
	{
		$this->assertStringContainsString(
			'$expired = $sent > 0 && time() - $sent >= $this->passwords->resetKeyLifetime();',
			(string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Profile/Controller/PasswordChange.php'),
			'the expiry condition is no longer the one forum_reset_key_expired() pins'
		);
	}

}
