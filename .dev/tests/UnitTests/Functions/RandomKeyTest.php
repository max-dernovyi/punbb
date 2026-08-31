<?php
/**
 * random_key() is the forum's only source of secrets: the PHP session id, the
 * CSRF token stored in the online table, the password salt, the generated
 * password of a verified registration and every activate_key mailed by the
 * password reset and the e-mail change.
 *
 * It produced all of them from uniqid()/rand()/mt_rand(). Mersenne Twister is
 * a published sequence whose state is recoverable from its own output, and
 * uniqid() is the clock — neither is a secret, and a reset key that can be
 * predicted is a password reset for somebody else's account.
 *
 * The alphabet and the length of each mode are a contract: activate_key is
 * mailed and typed, the session id has to satisfy the pattern in
 * forum_session_start(), and extensions call this function directly.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RandomKeyTest extends TestCase
{
	//
	// The three modes, with the alphabet each one promised before the change.
	//
	// name => [ $len, $readable, $hash, the pattern the whole key must match ]
	//
	public static function modes(): array
	{
		return array(
			'hash — session id and CSRF token' => array(32, false, true, '/\A[0-9a-f]{32}\z/'),
			'hash — cookie name suffix'		   => array(6, false, true, '/\A[0-9a-f]{6}\z/'),
			'readable — activate_key'		   => array(8, true, false, '/\A[A-Za-z0-9]{8}\z/'),
			'raw — password salt'			   => array(12, false, false, '/\A[\x21-\x7e]{12}\z/'),
		);
	}

	#[DataProvider('modes')]
	public function testTheModeKeepsItsAlphabetAndLength(int $len, bool $readable, bool $hash, string $pattern): void
	{
		for ($i = 0; $i < 50; ++$i)
		{
			$key = random_key($len, $readable, $hash);

			$this->assertSame($len, strlen($key));
			$this->assertMatchesRegularExpression($pattern, $key);
		}
	}

	//
	// The hash mode fed sha1() before, so it could never return more than 40
	// characters. A caller that asked for more still gets what it got.
	//
	public function testTheHashModeStaysWithinFortyCharacters(): void
	{
		$this->assertSame(40, strlen(random_key(40, false, true)));
		$this->assertSame(40, strlen(random_key(64, false, true)));
	}

	//
	// A CSRF token generated 40 characters wide by cookie_login().
	//
	public function testTheCsrfTokenWidthIsUnchanged(): void
	{
		$this->assertMatchesRegularExpression('/\A[0-9a-f]{40}\z/', random_key(40, false, true));
	}

	//
	// The session id has to survive the pattern forum_session_start() applies
	// to a supplied one, or the forum would replace the id it just generated.
	//
	public function testTheSessionIdMatchesTheAcceptedPattern(): void
	{
		$this->assertMatchesRegularExpression('/^[a-z0-9\-,]{16,32}$/i', random_key(32, false, true));
	}

	#[DataProvider('modes')]
	public function testKeysDoNotRepeat(int $len, bool $readable, bool $hash, string $pattern): void
	{
		$keys = array();
		for ($i = 0; $i < 200; ++$i)
			$keys[] = random_key($len, $readable, $hash);

		// The 6-character hash mode draws from 2^24, where 200 draws collide
		// about once in eight hundred runs by birthday alone: exact uniqueness
		// is not a property the generator can have there. A repeating
		// generator still fails - it would return far fewer than 195.
		$minimum = ($len < 8) ? 195 : 200;

		$this->assertGreaterThanOrEqual($minimum, count(array_unique($keys)));
	}

	//
	// The finding itself. mt_rand() and uniqid() are seeded once per process,
	// so replaying the seed replays every key the process would issue; a
	// CSPRNG has no seed to replay. mt_srand() proves the old generator was
	// reproducible and that the new one is not.
	//
	public function testTheKeysAreNotReproducibleFromTheMtRandSeed(): void
	{
		mt_srand(1234);
		$first = array(random_key(8, true), random_key(8, true), random_key(32, false, true), random_key(12));

		mt_srand(1234);
		$second = array(random_key(8, true), random_key(8, true), random_key(32, false, true), random_key(12));

		$this->assertNotSame($first, $second);

		// The negative control: with the generator it used to have, the same
		// seed produced the same keys.
		mt_srand(1234);
		$old_first = array(self::old_random_key(8, true), self::old_random_key(8, true));
		mt_srand(1234);
		$old_second = array(self::old_random_key(8, true), self::old_random_key(8, true));

		$this->assertSame($old_first, $old_second);
	}

	//
	// The generator as it was, so the test above compares against the real
	// thing rather than an assumption about it.
	//
	private static function old_random_key(int $len, bool $readable = false): string
	{
		$key = '';

		if ($readable)
		{
			$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

			for ($i = 0; $i < $len; ++$i)
				$key .= substr($chars, (mt_rand() % strlen($chars)), 1);
		}
		else
			for ($i = 0; $i < $len; ++$i)
				$key .= chr(mt_rand(33, 126));

		return $key;
	}

	//
	// A source guard, because the property above is about where the bytes come
	// from and a test can only sample the output.
	//
	public function testTheGeneratorReachesForACsprng(): void
	{
		$body = self::function_body('random_key');

		$this->assertStringContainsString('random_bytes(', $body);
		$this->assertStringContainsString('random_int(', $body);

		foreach (array('uniqid(', 'mt_rand(', 'rand(') as $weak)
			$this->assertStringNotContainsString($weak, str_replace(array('random_int(', 'random_bytes('), '', $body),
				'random_key() is back on a non-cryptographic generator');
	}

	private static function function_body(string $name): string
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/functions.php');
		$body = substr($source, (int) strpos($source, 'function '.$name.'('));

		return substr($body, 0, (int) strpos($body, "\n}\n"));
	}
}
