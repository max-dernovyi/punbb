<?php
/**
 * Contract for generate_form_token() and csrf_token_matches().
 *
 * The token is sha1($target.$secret), where the secret is the per-visit
 * online.csrf_token. Everything the gates rely on is here: a token nobody
 * without the secret can produce, a comparison that survives an array or a
 * missing parameter, and a secret that is never empty.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsrfTokenTest extends TestCase
{
	/** @var array<string, mixed> */
	private array $forum_user;

	protected function setUp(): void
	{
		global $forum_user;

		$this->forum_user = $forum_user;
		$forum_user['csrf_token'] = str_repeat('a', 40);
	}

	protected function tearDown(): void
	{
		global $forum_user;

		$forum_user = $this->forum_user;
	}

	private function secret(string $secret): void
	{
		global $forum_user;

		$forum_user['csrf_token'] = $secret;
	}

	public function testTheTokenIsTheTargetKeyedWithTheSecret(): void
	{
		$this->assertSame(sha1('markread7'.str_repeat('a', 40)), generate_form_token('markread7'));
	}

	/** The &amp; a forum_urls.php template carries is not in the URL the browser sends. */
	public function testTheEncodedAmpersandIsNormalisedAway(): void
	{
		$this->assertSame(
			generate_form_token('http://localhost/misc.php?action=markread&csrf_token=1'),
			generate_form_token('http://localhost/misc.php?action=markread&amp;csrf_token=1')
		);
	}

	public function testTwoUsersDoNotShareATokenForTheSameTarget(): void
	{
		$this->secret(str_repeat('b', 40));
		$moderator = generate_form_token('close12');

		$this->secret(str_repeat('c', 40));

		$this->assertNotSame($moderator, generate_form_token('close12'));
	}

	/**
	 * db_update.php adds online.csrf_token with a '' default, and the row is
	 * only ever written when it is created - so a visit that spanned the
	 * upgrade kept an empty secret, and sha1($target) is computable by anyone.
	 */
	#[DataProvider('emptySecretProvider')]
	public function testAnEmptySecretDoesNotProduceAGuessableToken(mixed $stored): void
	{
		global $forum_user;

		$forum_user['csrf_token'] = $stored;

		$token = generate_form_token('close12');

		// The pre-fix construction, which is what an attacker would compute.
		$this->assertNotSame(sha1('close12'), $token);
		$this->assertSame(40, strlen($token));
	}

	/** @return array<string, array{mixed}> */
	public static function emptySecretProvider(): array
	{
		return array(
			'empty string' => array(''),
			'absent'       => array(null),
		);
	}

	/** The fallback secret is a real secret: it is not the same one twice. */
	public function testTheFallbackSecretIsNotAFixedValue(): void
	{
		$this->secret('');
		$first = generate_form_token('close12');

		$this->secret('');
		$second = generate_form_token('close12');

		$this->assertNotSame($first, $second);
	}

	/** Once the fallback ran, the request keeps one secret, so a form validates against itself. */
	public function testTheFallbackSecretIsStableWithinTheRequest(): void
	{
		$this->secret('');

		$this->assertSame(generate_form_token('close12'), generate_form_token('close12'));
	}

	public function testTheMatcherAcceptsTheTokenTheFormCarries(): void
	{
		$this->assertTrue(csrf_token_matches(generate_form_token('logout2'), 'logout2'));
	}

	#[DataProvider('rejectedTokenProvider')]
	public function testTheMatcherRejects(mixed $token): void
	{
		$this->assertFalse(csrf_token_matches($token, 'logout2'));
	}

	/** @return array<string, array{mixed}> */
	public static function rejectedTokenProvider(): array
	{
		return array(
			'absent'          => array(null),
			'empty'           => array(''),
			'an array'        => array(array('a')),
			'an integer'      => array(1),
			'the wrong value' => array(sha1('logout2')),
			// ?csrf_token[]= used to reach a TypeError rather than the gate.
			'a nested array'  => array(array(array())),
		);
	}

	public function testATokenForAnotherTargetIsRejected(): void
	{
		$this->assertFalse(csrf_token_matches(generate_form_token('open12'), 'close12'));
	}

	public function testATokenForAnotherUserIsRejected(): void
	{
		$this->secret(str_repeat('b', 40));
		$other = generate_form_token('close12');

		$this->secret(str_repeat('c', 40));

		$this->assertFalse(csrf_token_matches($other, 'close12'));
	}
}
