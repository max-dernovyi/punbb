<?php
/**
 * Contract for generate_form_token() and csrf_token_matches().
 *
 * The token is sha1($target.$secret), where the secret is the per-visit
 * online.csrf_token. post.php verifies its own token with the matcher, so a
 * comparison that survives a missing parameter or an array is what stands
 * between a member's account and a page that posts in their name.
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

		$this->forum_user = is_array($forum_user) ? $forum_user : array();
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
