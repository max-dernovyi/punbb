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
		'include/common.php',
		'include/PunBB/Module/LegacyBridge/Site/LegacyCsrfTokens.php',
	);

	/**
	 * post.php opts out of the global gate, and used to make up for it only
	 * when the poster could moderate: a guest or a member posted with no token
	 * check at all, so any page could post in their name.
	 */
	public function testPostPhpChecksTheTokenForEveryPoster(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Post/Controller/PostController.php');

		$this->assertStringContainsString(
			'if (!$this->tokens->matches($request->post[\'csrf_token\'] ?? null, $this->urls->current()))'."\n\t\t\t".'$errors[] = self::string($strings, \'CSRF token mismatch\')->html;',
			$source,
			'PostController: the CSRF check is gone — retarget this guard'
		);
		$this->assertDoesNotMatchRegularExpression('#isAdministrator\(\)[^;]*csrf_token|moderating[^;]*csrf_token#', $source,
			'PostController: only moderators have their token checked');
		$this->assertDoesNotMatchRegularExpression('#csrf_token\'\]\s*(!==|===|!=|==)#', $source);
	}

	/** The opt-out is what makes the check above the only one post.php has: its route, and no page script. */
	public function testPostPhpIsStillTheOnlyPageOutsideTheGlobalGate(): void
	{
		$optouts = array();

		foreach (self::VERIFIERS as $file)
			if (strpos((string) file_get_contents(FORUM_ROOT.$file), 'define(\'FORUM_SKIP_CSRF_CONFIRM\'') !== false)
				$optouts[] = $file;

		$this->assertSame(array(), $optouts);
		$this->assertStringContainsString('), checksOwnToken: true);', (string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Post/Module.php'));
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

	/** The controllers compare tokens through the site's tokens, never inline. */
	public function testNoFileOfTheCoreComparesATokenInline(): void
	{
		$offenders = array();

		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(FORUM_ROOT.'include/PunBB', FilesystemIterator::SKIP_DOTS)) as $file)
			if (in_array($file->getExtension(), array('php', 'phtml'), true) && preg_match('#csrf_token\'\]\s*(!==|===|!=|==)#', (string) file_get_contents($file->getPathname())) === 1)
				$offenders[] = substr($file->getPathname(), strlen(FORUM_ROOT));

		$this->assertSame(array(), $offenders);
	}

	/** Deleting an avatar is a link: its controller checks the link's token, built over the member and the visitor's id, unless a posted token passed the gate. */
	public function testTheAvatarDeletionChecksItsLinkToken(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Profile/Controller/ProfileAdministration.php');

		$this->assertStringContainsString('!isset($request->post[\'csrf_token\']) && !$this->tokens->matches($request->query[\'csrf_token\'] ?? null, \'delete_avatar\'.$user->id().$this->visitor->id())', $source,
			'ProfileAdministration: the link token check is gone — retarget this guard');
		$this->assertStringContainsString('$this->tokens->token(\'delete_avatar\'.$user->id().$this->visitor->id())', (string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Profile/Controller/ProfileSections.php'),
			'ProfileSections: the link does not carry the token the controller checks');
		$this->assertDoesNotMatchRegularExpression('#csrf_token\'\]\s*(!==|===|!=|==)#', $source);
	}

	/** A rebuild cycle is a link: its controller checks the link's token, built over the administrator's id, through the site's tokens. */
	public function testTheRebuildCycleChecksItsLinkToken(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Reindex/Controller/ReindexController.php');

		$this->assertStringContainsString('!$this->tokens->matches($request->query[\'csrf_token\'] ?? null, $this->target())', $source,
			'ReindexController: the link token check is gone — retarget this guard');
		$this->assertStringContainsString('return \'reindex\'.$this->visitor->id();', $source,
			'ReindexController: the link token does not carry the administrator\'s id');
		$this->assertDoesNotMatchRegularExpression('#csrf_token\'\]\s*(!==|===|!=|==)#', $source);
	}

	/** Signing out is a link: its controller checks the link's token, built over the member's id, through the site's tokens, unless a posted token passed the gate. */
	public function testTheLogoutChecksItsLinkToken(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Login/Controller/LoginController.php');

		$this->assertStringContainsString('!isset($request->post[\'csrf_token\']) && !$this->tokens->matches($request->query[\'csrf_token\'] ?? null, \'logout\'.$this->visitor->id())', $source,
			'LoginController: the logout token check is gone — retarget this guard');
		$this->assertDoesNotMatchRegularExpression('#csrf_token\'\]\s*(!==|===|!=|==)#', $source);
	}

	/** Removing a ban is a link: its controller checks the link's token, built over the ban and the acting user's id, through the site's tokens. */
	public function testTheBanRemovalChecksItsLinkToken(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Bans/Controller/BansController.php');

		$this->assertStringContainsString('!isset($request->post[\'csrf_token\']) && !$this->tokens->matches($request->query[\'csrf_token\'] ?? null, \'del_ban\'.$banId.$this->visitor->id())', $source,
			'BansController: the link token check is gone — retarget this guard');
		$this->assertDoesNotMatchRegularExpression('#csrf_token\'\]\s*(!==|===|!=|==)#', $source);
	}

	/**
	 * Removing a group without members is a link, which removed the group on a
	 * plain GET: its controller checks the link's token, built over the group
	 * and the administrator's id, unless a posted token passed the gate.
	 */
	public function testTheGroupRemovalChecksItsLinkToken(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Groups/Controller/GroupsController.php');

		$this->assertStringContainsString('!isset($request->post[\'csrf_token\']) && !$this->tokens->matches($request->query[\'csrf_token\'] ?? null, \'del_group\'.$id.$this->visitor->id())', $source,
			'GroupsController: the link token check is gone — retarget this guard');
		$this->assertDoesNotMatchRegularExpression('#csrf_token\'\]\s*(!==|===|!=|==)#', $source);
	}

	/**
	 * Marking read and subscribing are links: the controller checks each link's
	 * token, built over what it changes and the member's id, unless a posted
	 * token passed the gate.
	 */
	public function testTheMiscLinksCheckTheirTokens(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Misc/Controller/MiscController.php');

		$this->assertStringContainsString('if (isset($request->post[\'csrf_token\']) || $this->tokens->matches($request->query[\'csrf_token\'] ?? null, $target))', $source,
			'MiscController: the link token check is gone — retarget this guard');
		foreach (array('$this->confirmUntokened($request, \'markread\'.$this->visitor->id())', '$this->confirmUntokened($request, \'markforumread\'.$forumId.$this->visitor->id())', '$this->confirmUntokened($request, $parameter.$id.$userId)') as $check)
			$this->assertStringContainsString($check, $source, 'MiscController: a link token does not carry what it changes and the member\'s id');
		$this->assertDoesNotMatchRegularExpression('#csrf_token\'\]\s*(!==|===|!=|==)#', $source);
	}

	/**
	 * Opening, closing, sticking and unsticking a topic are links: the
	 * moderation checks each link's token, built over what it does, the topic
	 * and the moderator's id, unless a posted token passed the gate.
	 */
	public function testTheModerationLinksCheckTheirTokens(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Moderate/Controller/TopicsModeration.php');

		foreach (array('!isset($request->post[\'csrf_token\']) && !$this->tokens->matches($request->query[\'csrf_token\'] ?? null, ($closing ? \'close\' : \'open\').$topicId.$this->visitor->id())',
			'!isset($request->post[\'csrf_token\']) && !$this->tokens->matches($request->query[\'csrf_token\'] ?? null, $action.$topicId.$this->visitor->id())') as $check)
			$this->assertStringContainsString($check, $source, 'TopicsModeration: a link token check is gone — retarget this guard');
		$this->assertDoesNotMatchRegularExpression('#csrf_token\'\]\s*(!==|===|!=|==)#', $source);
	}

	/** Switching an extension is a link: its controller checks the link's token, built over the extension and the administrator's id. */
	public function testTheExtensionSwitchChecksItsLinkToken(): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.'include/PunBB/Module/Extensions/Controller/ExtensionsController.php');

		$this->assertStringContainsString('!isset($request->post[\'csrf_token\']) && !$this->tokens->matches($request->query[\'csrf_token\'] ?? null, \'flip\'.$id.$this->visitor->id())', $source,
			'ExtensionsController: the link token check is gone — retarget this guard');
		$this->assertDoesNotMatchRegularExpression('#csrf_token\'\]\s*(!==|===|!=|==)#', $source);
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
	// file => [ the target as it is built now, the target as it was, how the file names the acting user ]
	//
	public static function moderatorTokens(): array
	{
		return array(
			'TopicController open/close link'		=> array('include/PunBB/Module/Viewtopic/Controller/TopicController.php', '$this->tokens->token($close[0].$id.$userId)', '$this->tokens->token($close[0].$id)', '.$userId'),
			'TopicController stick/unstick link'	=> array('include/PunBB/Module/Viewtopic/Controller/TopicController.php', '$this->tokens->token($stick[0].$id.$userId)', '$this->tokens->token($stick[0].$id)', '.$userId'),
			'TopicsModeration open/close link'		=> array('include/PunBB/Module/Moderate/Controller/TopicsModeration.php', '($closing ? \'close\' : \'open\').$topicId.$this->visitor->id()', '($closing ? \'close\' : \'open\').$topicId)', '.$this->visitor->id()'),
			'TopicsModeration stick/unstick link'	=> array('include/PunBB/Module/Moderate/Controller/TopicsModeration.php', '$action.$topicId.$this->visitor->id()', '$action.$topicId)', '.$this->visitor->id()'),
			'BansController del_ban link'			=> array('include/PunBB/Module/Bans/Controller/BansController.php', '$this->tokens->token(\'del_ban\'.$ban->id().$this->visitor->id())', '$this->tokens->token(\'del_ban\'.$ban->id())', '.$this->visitor->id()'),
			'GroupsController del_group link'		=> array('include/PunBB/Module/Groups/Controller/GroupsController.php', '$this->tokens->token(\'del_group\'.$group->id().$this->visitor->id())', '$this->tokens->token(\'del_group\'.$group->id())', '.$this->visitor->id()'),
			'ExtensionBoxes flip link'	=> array('include/PunBB/Module/Extensions/View/ExtensionBoxes.php', '$this->tokens->token(\'flip\'.$id.$this->visitor->id())', '$this->tokens->token(\'flip\'.$id)', '.$this->visitor->id()'),
		);
	}

	#[DataProvider('moderatorTokens')]
	public function testTheModeratorTokenNamesTheModerator(string $file, string $bound, string $unbound, string $user): void
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
	public function testTheGuardWouldSeeTheUnboundToken(string $file, string $bound, string $unbound, string $user): void
	{
		$unwrapped = str_replace($user, '', (string) file_get_contents(FORUM_ROOT.$file));

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
