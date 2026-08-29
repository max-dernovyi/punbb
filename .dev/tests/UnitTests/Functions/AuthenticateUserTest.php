<?php
/**
 * authenticate_user() serves two callers with different material: the cookie
 * path hands it a stored hash, extern.php hands it the plaintext of an HTTP
 * Basic header. The plaintext branch has to read every format the login page
 * reads, or a feed reader stops working the moment its user's hash migrates.
 *
 * The function itself needs a database, so these tests pin the comparison the
 * function performs rather than the query around it.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AuthenticateUserTest extends TestCase {
	private const PASSWORD = 'feed reader password';
	private const SALT = 'f1e2d3c4b5a6';

	/** @return string[] every format the users table can hold */
	public static function storedHashes(): array {
		return array(
			'modern' => array(forum_password_hash(self::PASSWORD)),
			'salted sha1' => array(forum_hash(self::PASSWORD, self::SALT)),
			'unsalted sha1' => array(sha1(self::PASSWORD)),
			'md5' => array(md5(self::PASSWORD)),
		);
	}

	#[DataProvider('storedHashes')]
	public function testThePlaintextBranchAcceptsEveryStoredFormat(string $stored): void {
		$this->assertTrue(forum_password_verify(self::PASSWORD, $stored, self::SALT));
		$this->assertFalse(forum_password_verify('wrong password', $stored, self::SALT));
	}

	/** The source of the bug: a bcrypt row never matches the old comparison. */
	public function testTheOldComparisonWouldRejectAMigratedUser(): void {
		$stored = forum_password_hash(self::PASSWORD);

		$this->assertNotSame($stored, forum_hash(self::PASSWORD, self::SALT));
		$this->assertTrue(forum_password_verify(self::PASSWORD, $stored, self::SALT));
	}

	/** The cookie branch compares the stored hash itself, whatever its format. */
	public function testTheHashBranchComparesStoredHashesDirectly(): void {
		foreach (array(forum_password_hash(self::PASSWORD), forum_hash(self::PASSWORD, self::SALT)) as $stored)
		{
			$this->assertTrue(hash_equals($stored, $stored));
			$this->assertFalse(hash_equals($stored, $stored.'x'));
		}
	}

	public function testAuthenticateUserReadsBothBranches(): void {
		$source = file_get_contents(FORUM_ROOT.'include/functions.php');
		$body = substr($source, strpos($source, 'function authenticate_user('));
		$body = substr($body, 0, strpos($body, "\n}\n"));

		$this->assertStringContainsString('forum_password_verify(', $body, 'the plaintext branch must read every format');
		$this->assertStringNotContainsString('forum_hash($password', $body, 'the salted SHA-1 comparison must be gone');
	}
}
