<?php
/**
 * Where the CSRF gates are, and what each of them covers.
 *
 * The forum has two gates: `include/common.php` rejects any POST without a
 * token for the current URL, and the state changes reachable by a link verify
 * a token built from a per-action string. Both need a live forum to exercise,
 * so what they are made of is pinned here.
 *
 * Every guard is checked against the shape the line had before the fix, so a
 * guard that stopped matching fails instead of passing silently.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsrfGuardTest extends TestCase
{
	/**
	 * Every script that verifies a CSRF token itself.
	 *
	 * @var list<string>
	 */
	private const VERIFIERS = array(
		'admin/bans.php', 'admin/extensions.php', 'admin/reindex.php',
		'include/common.php', 'login.php', 'misc.php', 'moderate.php',
		'post.php', 'profile.php',
	);

	/**
	 * post.php opts out of the global gate for the whole script, and used to
	 * make up for it only when the poster could moderate: a guest or a member
	 * posted with no token check at all, so any page could post in their name.
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

		foreach (self::VERIFIERS as $file)
			if (strpos((string) file_get_contents(FORUM_ROOT.$file), 'define(\'FORUM_SKIP_CSRF_CONFIRM\'') !== false)
				$optouts[] = $file;

		$this->assertSame(array('post.php'), $optouts);
	}

	/**
	 * A raw `!==` against a token is both non-constant-time and a silent pass
	 * when the parameter arrives as an array. csrf_token_matches() is the one
	 * comparison, and it is hash_equals() behind an is_string().
	 */
	#[DataProvider('verifierProvider')]
	public function testTheTokenIsNeverComparedInline(string $file): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.$file);

		$this->assertStringContainsString('csrf_token_matches(', $source,
			$file.': no token verification left — retarget this guard');
		$this->assertDoesNotMatchRegularExpression('#csrf_token\'\]\s*(!==|===|!=|==)#', $source,
			$file.': a CSRF token is compared inline');
	}

	/** @return array<string, array{string}> */
	public static function verifierProvider(): array
	{
		$cases = array();

		foreach (self::VERIFIERS as $file)
			$cases[$file] = array($file);

		return $cases;
	}

	public function testTheMatcherComparesInConstantTime(): void
	{
		$this->assertStringContainsString(
			'return is_string($token) && hash_equals(generate_form_token($target), $token);',
			(string) file_get_contents(FORUM_ROOT.'include/functions.php')
		);
	}

	//
	// The per-action tokens. Every one of them mixes the acting user's id into
	// the target string; the four moderator links were the exception.
	//
	// file => [ the target as it is built now, the target as it was ]
	//
	public static function moderatorTokens(): array
	{
		return array(
			'viewtopic.php open link'   => array('viewtopic.php', 'generate_form_token(\'open\'.$id.$forum_user[\'id\'])', 'generate_form_token(\'open\'.$id)'),
			'viewtopic.php close link'  => array('viewtopic.php', 'generate_form_token(\'close\'.$id.$forum_user[\'id\'])', 'generate_form_token(\'close\'.$id)'),
			'viewtopic.php stick link'  => array('viewtopic.php', 'generate_form_token(\'stick\'.$id.$forum_user[\'id\'])', 'generate_form_token(\'stick\'.$id)'),
			'viewtopic.php unstick link'=> array('viewtopic.php', 'generate_form_token(\'unstick\'.$id.$forum_user[\'id\'])', 'generate_form_token(\'unstick\'.$id)'),
			'moderate.php open/close'   => array('moderate.php', '($action ? \'close\' : \'open\').$topic_id.$forum_user[\'id\']', '($action ? \'close\' : \'open\').$topic_id)'),
			'moderate.php stick'        => array('moderate.php', '\'stick\'.$stick.$forum_user[\'id\']', '\'stick\'.$stick)'),
			'moderate.php unstick'      => array('moderate.php', '\'unstick\'.$unstick.$forum_user[\'id\']', '\'unstick\'.$unstick)'),
			'bans.php del_ban link'     => array('admin/bans.php', 'generate_form_token(\'del_ban\'.$cur_ban[\'id\'].$forum_user[\'id\'])', 'generate_form_token(\'del_ban\'.$cur_ban[\'id\'])'),
			'extensions.php flip link'  => array('admin/extensions.php', 'generate_form_token(\'flip\'.$id.$forum_user[\'id\'])', 'generate_form_token(\'flip\'.$id)'),
		);
	}

	#[DataProvider('moderatorTokens')]
	public function testTheModeratorTokenNamesTheModerator(string $file, string $bound, string $unbound): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.$file);

		$this->assertStringContainsString($bound, $source,
			$file.': the moderator token is gone — retarget this guard');
		$this->assertStringNotContainsString($unbound, $source,
			$file.': the moderator token does not carry the user id');
	}

	/**
	 * The negative control: the "unbound" needle has to be able to find the
	 * line it describes, otherwise the assertion above passes for the wrong
	 * reason.
	 */
	#[DataProvider('moderatorTokens')]
	public function testTheGuardWouldSeeTheUnboundToken(string $file, string $bound, string $unbound): void
	{
		$unwrapped = str_replace('.$forum_user[\'id\']', '', (string) file_get_contents(FORUM_ROOT.$file));

		$this->assertStringContainsString($unbound, $unwrapped,
			$file.': the guard cannot see the unbound form of '.$bound);
	}

	/**
	 * The online row is written only when it is created, so the '' default
	 * db_update.php gives the csrf_token column would have survived the whole
	 * visit — and a token keyed on '' is sha1($target), which anyone can build.
	 * Both branches that refresh an existing row refill it.
	 */
	public function testAnEmptySecretIsRefilledOnBothBranches(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/functions.php');

		$this->assertSame(3, substr_count($source, 'if (!isset($forum_user[\'csrf_token\']) || $forum_user[\'csrf_token\'] === \'\')'),
			'include/functions.php: the empty-secret guards are not on both online-row branches and generate_form_token()');
		$this->assertSame(2, substr_count($source, '$query[\'SET\'] .= \', csrf_token=\\\'\'.$forum_user[\'csrf_token\'].\'\\\'\';'),
			'include/functions.php: the refilled secret is not written back to the online row');
	}
}
