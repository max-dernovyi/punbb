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
	// Both branches that check a mailed key.
	//
	public static function keyChecks(): array
	{
		return array(
			'password reset' => array('$lang_profile[\'Pass key bad\']'),
			'e-mail change'	 => array('$lang_profile[\'E-mail key bad\']'),
		);
	}

	#[DataProvider('keyChecks')]
	public function testTheBranchComparesInConstantTime(string $message): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'profile.php');
		$branch = substr($source, 0, (int) strpos($source, $message));
		$branch = substr($branch, (int) strrpos($branch, "if (isset(\$_GET['key']))"));

		$this->assertStringContainsString('hash_equals((string) $user[\'activate_key\'], $key)', $branch);
		$this->assertStringNotContainsString('$key != $user[\'activate_key\']', $branch,
			'the branch is back on the loose comparison');
	}

	#[DataProvider('keyChecks')]
	public function testTheKeyIsReadAsAString(string $message): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'profile.php');
		$branch = substr($source, 0, (int) strpos($source, $message));
		$branch = substr($branch, (int) strrpos($branch, "if (isset(\$_GET['key']))"));

		// hash_equals() raises a TypeError on ?key[]=, which the loose
		// comparison merely returned true for.
		$this->assertStringContainsString('is_string($_GET[\'key\'])', $branch);
	}

	//
	// The reset key expires; the registration key, which has no mail timestamp
	// behind it, is left to the three-day prune in register.php.
	//
	public function testTheResetKeyExpires(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'profile.php');

		$this->assertStringContainsString('$key_expired', $source);
		$this->assertStringContainsString('FORUM_PASSWORD_RESET_TTL',
			(string) file_get_contents(FORUM_ROOT.'include/functions.php'));
	}

	public function testTheWindowMatchesTheOneTheResendIsRefusedFor(): void
	{
		$this->assertSame(3600, FORUM_PASSWORD_RESET_TTL);
		$this->assertStringContainsString('$forgot_pass_timeout = FORUM_PASSWORD_RESET_TTL;',
			(string) file_get_contents(FORUM_ROOT.'login.php'),
			'a key that expired could not be replaced immediately');
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

	/** profile.php has to reach the decision through the function, not a copy of it. */
	public function testProfileAsksTheFunction(): void
	{
		$this->assertStringContainsString(
			'$key_expired = forum_reset_key_expired(',
			(string) file_get_contents(FORUM_ROOT.'profile.php'),
			'the expiry condition is inlined again, so nothing tests what runs'
		);
	}
}
