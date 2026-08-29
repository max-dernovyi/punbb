<?php
/**
 * Password storage: a new algorithm added next to the old ones, not replacing
 * them. Every hash the forum has ever written must still authenticate, and a
 * verified password in an older format must be reported as needing a rewrite.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class PasswordHashTest extends TestCase {
	private const PASSWORD = 'correct horse battery staple';
	private const SALT = 'a1b2c3d4e5f6';

	public function testAModernHashVerifies(): void {
		$hash = forum_password_hash(self::PASSWORD);

		$this->assertTrue(forum_password_verify(self::PASSWORD, $hash, self::SALT));
		$this->assertFalse(forum_password_verify('wrong', $hash, self::SALT));
	}

	public function testAModernHashIsSaltedByPhpAndNeverRepeats(): void {
		$this->assertNotSame(forum_password_hash(self::PASSWORD), forum_password_hash(self::PASSWORD));
	}

	/** 1.4: sha1($salt.sha1($password)), what forum_hash() still produces. */
	public function testASaltedSha1Verifies(): void {
		$hash = forum_hash(self::PASSWORD, self::SALT);

		$this->assertTrue(forum_password_verify(self::PASSWORD, $hash, self::SALT));
		$this->assertFalse(forum_password_verify('wrong', $hash, self::SALT));
		$this->assertFalse(forum_password_verify(self::PASSWORD, $hash, 'other-salt'));
	}

	/** 1.3: an unsalted sha1. */
	public function testAnUnsaltedSha1Verifies(): void {
		$hash = sha1(self::PASSWORD);

		$this->assertTrue(forum_password_verify(self::PASSWORD, $hash, self::SALT));
		$this->assertFalse(forum_password_verify('wrong', $hash, self::SALT));
	}

	/** 1.2: an md5. */
	public function testAnMd5Verifies(): void {
		$hash = md5(self::PASSWORD);

		$this->assertTrue(forum_password_verify(self::PASSWORD, $hash, self::SALT));
		$this->assertFalse(forum_password_verify('wrong', $hash, self::SALT));
	}

	public function testAnEmptyHashNeverVerifies(): void {
		$this->assertFalse(forum_password_verify(self::PASSWORD, '', self::SALT));
		$this->assertFalse(forum_password_verify('', '', ''));
	}

	public function testOnlyLegacyHashesAskToBeRewritten(): void {
		$this->assertFalse(forum_password_needs_rehash(forum_password_hash(self::PASSWORD)));

		$this->assertTrue(forum_password_needs_rehash(forum_hash(self::PASSWORD, self::SALT)));
		$this->assertTrue(forum_password_needs_rehash(sha1(self::PASSWORD)));
		$this->assertTrue(forum_password_needs_rehash(md5(self::PASSWORD)));
	}

	public function testFormatDetectionSeparatesTheTwoWorlds(): void {
		$this->assertTrue(forum_password_is_modern(forum_password_hash(self::PASSWORD)));

		$this->assertFalse(forum_password_is_modern(forum_hash(self::PASSWORD, self::SALT)));
		$this->assertFalse(forum_password_is_modern(sha1(self::PASSWORD)));
		$this->assertFalse(forum_password_is_modern(md5(self::PASSWORD)));
		$this->assertFalse(forum_password_is_modern(''));
	}

	/**
	 * forum_hash() also produces the login cookie's expiry hash, so it must keep
	 * its own contract whatever happens to password storage.
	 */
	public function testForumHashIsUnchanged(): void {
		$this->assertSame(sha1(self::SALT.sha1(self::PASSWORD)), forum_hash(self::PASSWORD, self::SALT));
	}
}
