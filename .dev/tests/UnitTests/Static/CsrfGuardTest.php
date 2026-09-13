<?php
/**
 * The one CSRF gate post.php does not inherit.
 *
 * `include/common.php` rejects any POST without a token for the current URL,
 * and post.php opts out of it for the whole script with FORUM_SKIP_CSRF_CONFIRM.
 * The check it makes up with used to run only for moderators, so the gate is
 * pinned here against the shape the line had before the fix.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class CsrfGuardTest extends TestCase
{
	/**
	 * post.php used to make up for the opt-out only when the poster could
	 * moderate: a guest or a member posted with no token check at all, so any
	 * page a logged-in member visited could post in their name.
	 */
	public function testPostPhpChecksTheTokenForEveryPoster(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'post.php');

		$this->assertStringContainsString(
			'if (!csrf_token_matches($_POST[\'csrf_token\'] ?? null, get_current_url()))'."\n\t\t".'$errors[] = $lang_post[\'CSRF token mismatch\'];',
			$source,
			'post.php: the CSRF check is gone — retarget this guard'
		);
		$this->assertStringNotContainsString('$forum_user[\'is_admmod\'] && (!isset($_POST[\'csrf_token\'])', $source,
			'post.php: only moderators have their token checked');
	}

	/** The opt-out is what makes the check above the only one post.php has. */
	public function testPostPhpIsStillTheOnlyScriptOutsideTheGlobalGate(): void
	{
		$optouts = array();

		foreach (glob(FORUM_ROOT.'*.php') as $file)
			if (strpos((string) file_get_contents($file), 'define(\'FORUM_SKIP_CSRF_CONFIRM\'') !== false)
				$optouts[] = basename($file);

		$this->assertSame(array('post.php'), $optouts);
	}

	/** The matcher is hash_equals() behind an is_string(), not a raw comparison. */
	public function testTheMatcherIsConstantTimeAndTypeSafe(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/functions.php');
		$body = substr($source, (int) strpos($source, 'function csrf_token_matches('));

		$this->assertStringContainsString('is_string($token) && hash_equals(generate_form_token($target), $token);', $body);
	}
}
