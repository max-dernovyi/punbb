<?php
/**
 * The login cookie is `base64(user_id|password_hash|expire|authenticator)` and
 * the authenticator is the only thing standing between the four fields and a
 * forged login.
 *
 * It used to be sha1($salt.$password_hash.forum_hash($expire, $salt)) — the id
 * beside it was not covered. On a forum upgraded from PunBB 1.4 or earlier,
 * where forum_password_verify() still accepts an unsalted sha1() or md5() of
 * the password and those rows carry salt '', two accounts that chose the same
 * password hold byte-identical authenticators. Editing the id field of one's
 * own cookie then logs in as the other account.
 *
 * forum_cookie_hash() puts the id inside the hash.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class CookieHashTest extends TestCase
{
	private const EXPIRE = 1893456000;

	//
	// The two accounts of the finding: an upgraded forum, the same password,
	// the legacy unsalted hash, the empty salt every 1.2-era row carries.
	//
	private const LEGACY_HASH = '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8';	// sha1('password')
	private const LEGACY_SALT = '';

	public function testTheAuthenticatorIsBoundToTheUserId(): void
	{
		$victim = forum_cookie_hash(2, self::LEGACY_HASH, self::EXPIRE, self::LEGACY_SALT);
		$attacker = forum_cookie_hash(3, self::LEGACY_HASH, self::EXPIRE, self::LEGACY_SALT);

		$this->assertNotSame($victim, $attacker,
			'two accounts sharing a legacy hash still produce the same authenticator');
	}

	//
	// The negative control: the construction as it was, on the same inputs.
	// Without it the assertion above could pass for any reason at all.
	//
	public function testTheOldConstructionWasNotBoundToTheUserId(): void
	{
		$victim = self::old_cookie_hash(self::LEGACY_HASH, self::EXPIRE, self::LEGACY_SALT);
		$attacker = self::old_cookie_hash(self::LEGACY_HASH, self::EXPIRE, self::LEGACY_SALT);

		$this->assertSame($victim, $attacker);
	}

	//
	// A salted row is safe from the swap by accident — the salt differs per
	// user — but the binding must not depend on that.
	//
	public function testTheBindingHoldsForSaltedRows(): void
	{
		$hash = forum_password_hash('password');

		$this->assertNotSame(
			forum_cookie_hash(2, $hash, self::EXPIRE, 'a1b2c3d4e5f6'),
			forum_cookie_hash(3, $hash, self::EXPIRE, 'a1b2c3d4e5f6'));
	}

	//
	// The expiry is what stops a captured cookie from being handed back with a
	// later timestamp, so it stays covered.
	//
	public function testTheExpiryIsCovered(): void
	{
		$this->assertNotSame(
			forum_cookie_hash(2, self::LEGACY_HASH, self::EXPIRE, 'a1b2c3d4e5f6'),
			forum_cookie_hash(2, self::LEGACY_HASH, self::EXPIRE + 1, 'a1b2c3d4e5f6'));
	}

	//
	// And the password hash, which is what a password change invalidates.
	//
	public function testThePasswordHashIsCovered(): void
	{
		$this->assertNotSame(
			forum_cookie_hash(2, forum_password_hash('old'), self::EXPIRE, 'a1b2c3d4e5f6'),
			forum_cookie_hash(2, forum_password_hash('new'), self::EXPIRE, 'a1b2c3d4e5f6'));
	}

	public function testTheIdIsReadAsANumberSoTheFieldCannotBeShifted(): void
	{
		$this->assertSame(
			forum_cookie_hash(2, self::LEGACY_HASH, self::EXPIRE, 'a1b2c3d4e5f6'),
			forum_cookie_hash('2', self::LEGACY_HASH, (string) self::EXPIRE, 'a1b2c3d4e5f6'));
	}

	//
	// Every site that writes the cookie and the one that reads it have to agree
	// on the construction, so none of them may still build it by hand.
	//
	public function testEveryCookieSiteUsesTheHelper(): void
	{
		foreach (array('login.php', 'register.php', 'profile.php', 'include/functions.php') as $file)
		{
			$source = (string) file_get_contents(FORUM_ROOT.$file);

			$this->assertStringContainsString('forum_cookie_hash(', $source, $file);
			$this->assertStringNotContainsString('.forum_hash($expire,', $source,
				$file.': the cookie authenticator is still built inline');
		}
	}

	//
	// cookie_login() compares the authenticator it recomputed with the one the
	// browser sent; that comparison is a secret comparison.
	//
	public function testTheCookieIsVerifiedInConstantTime(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/functions.php');
		$body = substr($source, (int) strpos($source, 'function cookie_login('));
		$body = substr($body, 0, (int) strpos($body, "\n}\n"));

		$this->assertStringContainsString('hash_equals(forum_cookie_hash(', $body);
	}

	private static function old_cookie_hash(string $password_hash, int $expire, string $salt): string
	{
		return sha1($salt.$password_hash.forum_hash($expire, $salt));
	}
}
