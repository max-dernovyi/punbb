<?php
/**
 * Covers the parts of the functional pass (.dev/tests/Integration/user_flows.php)
 * that can be judged without a running site: the HTML the pass has to read to
 * drive a form, how it resolves and follows what the forum answers with, and the
 * content markers it asserts on.
 *
 * The pass itself is `make test-flows`; this pins the contract it uses, so a
 * form parser that stops understanding a page the forum renders, or a marker
 * that drifts out of the product, fails here instead of only in the matrix.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class UserFlowsTest extends TestCase {
	public static function setUpBeforeClass(): void {
		require_once FORUM_ROOT.'.dev/tests/Integration/user_flows.php';
	}

	/** Sharing the install matrix's prefix would let one run drop the other's tables. */
	public function testItGetsStorageOfItsOwn(): void {
		$prefixes = array();

		foreach (install_matrix_drivers() as $spec)
			$prefixes[] = $spec['prefix'];

		$this->assertNotContains(USER_FLOWS_PREFIX, $prefixes);
		$this->assertSame(USER_FLOWS_PREFIX, user_flows_spec()['prefix']);
		$this->assertSame('mysql', user_flows_spec()['backend']);
	}

	/** The member the pass registers has to survive register.php's validation. */
	public function testTheMemberAccountPassesTheRegistrationValidation(): void {
		$this->assertGreaterThanOrEqual(2, utf8_strlen(USER_FLOWS_USERNAME));
		$this->assertLessThanOrEqual(25, utf8_strlen(USER_FLOWS_USERNAME));
		$this->assertGreaterThanOrEqual(4, utf8_strlen(USER_FLOWS_PASSWORD));
		$this->assertNotSame(strtolower(INSTALL_MATRIX_USERNAME), strtolower(USER_FLOWS_USERNAME));

		require_once FORUM_ROOT.'include/email.php';
		$this->assertTrue((bool) is_valid_email(USER_FLOWS_EMAIL));
	}

	/**
	 * The topic subject and the searchable keyword are asserted on in feeds,
	 * search results and rendered pages, so both have to survive the forum's
	 * own limits: the subject its column, the keyword the index.
	 */
	public function testTheContentMarkersFitWhatTheForumAccepts(): void {
		$this->assertLessThanOrEqual(FORUM_SUBJECT_MAXIMUM_LENGTH, utf8_strlen(USER_FLOWS_SUBJECT));

		require_once FORUM_ROOT.'include/search_idx.php';
		$this->assertGreaterThanOrEqual(FORUM_SEARCH_MIN_WORD, strlen(USER_FLOWS_KEYWORD));
		$this->assertLessThanOrEqual(FORUM_SEARCH_MAX_WORD, strlen(USER_FLOWS_KEYWORD));
		$this->assertSame(array(USER_FLOWS_KEYWORD), array_values(split_words(USER_FLOWS_KEYWORD)));
	}

	/** Every marker but the emoji has to be storable in a utf8mb3 column. */
	public function testOnlyTheEmojiMarkerIsOutsideTheBmp(): void {
		$markers = array(USER_FLOWS_SUBJECT, USER_FLOWS_REPLY, USER_FLOWS_EDITED, USER_FLOWS_LOCATION,
			USER_FLOWS_SIGNATURE, USER_FLOWS_BOARD_TITLE, USER_FLOWS_FORUM_NAME, USER_FLOWS_IDN_URL);

		foreach ($markers as $marker)
			$this->assertSame(0, preg_match('/[\x{10000}-\x{10FFFF}]/u', $marker), $marker);

		$this->assertSame(1, preg_match('/[\x{10000}-\x{10FFFF}]/u', USER_FLOWS_EMOJI));
	}

	/** The punycode the pass looks for is what the forum's own converter produces. */
	public function testThePunycodeMarkerMatchesTheConverter(): void {
		$this->assertSame('http://'.USER_FLOWS_IDN_PUNYCODE.'/', forum_idna_encode(USER_FLOWS_IDN_URL));
	}

	/** A 1x1 PNG, small enough for the avatar limits a fresh install writes. */
	public function testTheAvatarFixtureIsAPngWithinTheDefaultLimits(): void {
		$png = (string) base64_decode(USER_FLOWS_AVATAR_PNG, true);
		$size = getimagesizefromstring($png);

		$this->assertNotFalse($size);
		$this->assertSame(IMAGETYPE_PNG, $size[2]);
		$this->assertLessThanOrEqual(60, $size[0]);
		$this->assertLessThanOrEqual(60, $size[1]);
		$this->assertLessThanOrEqual(15360, strlen($png));
	}

	// ------------------------------------------------------- the form parser --

	public function testItReadsTheFieldsAFormSubmits(): void {
		$body = '<form class="frm-form" method="post" action="http://localhost/post.php?tid=2">'.
			'<input type="hidden" name="form_sent" value="1" />'.
			'<input type="hidden" name="csrf_token" value="deadbeef" />'.
			'<input type="text" name="req_subject" value="Ümlaut &amp; more" />'.
			'<textarea name="req_message">line one&#10;[b]bold&#10;</textarea>'.
			'<input type="checkbox" name="hide_smilies" value="1" />'.
			'<input type="checkbox" name="subscribe" value="1" checked="checked" />'.
			'<select name="move_to_forum"><option value="1">One</option><option value="2" selected="selected">Two</option></select>'.
			'<input type="file" name="req_file" />'.
			'<input type="submit" name="submit" value="Submit" />'.
			'</form>';

		$form = user_flows_find_form($body, 'name="req_message"');

		$this->assertNotNull($form);
		$this->assertSame('post', $form['method']);
		$this->assertSame('http://localhost/post.php?tid=2', $form['action']);
		$this->assertSame('1', $form['fields']['form_sent']);
		$this->assertSame('deadbeef', $form['fields']['csrf_token']);
		$this->assertSame('Ümlaut & more', $form['fields']['req_subject']);
		$this->assertSame("line one\n[b]bold\n", $form['fields']['req_message']);
		$this->assertSame('2', $form['fields']['move_to_forum']);

		// An unchecked box is not submitted; a submit button is the caller's to press.
		$this->assertArrayNotHasKey('hide_smilies', $form['fields']);
		$this->assertSame('1', $form['fields']['subscribe']);
		$this->assertArrayNotHasKey('submit', $form['fields']);
		$this->assertArrayNotHasKey('req_file', $form['fields']);
	}

	/** Several forms on one page: the pass picks by a field, not by position. */
	public function testItPicksTheFormCarryingTheField(): void {
		$body = '<form method="post" action="a.php"><input type="text" name="new_ban_user" /></form>'.
			'<form method="get" action="b.php"><input type="text" name="keywords" /></form>';

		$this->assertSame('a.php', user_flows_find_form($body, 'name="new_ban_user"')['action']);
		$this->assertSame('get', user_flows_find_form($body, 'name="keywords"')['method']);
		$this->assertNull(user_flows_find_form($body, 'name="nothing_like_it"'));
	}

	public function testItReadsTheValidationErrorsAPageLists(): void {
		$body = '<ul class="error-list"><li class="warn"><span>Username too short.</span></li>'.
			'<li class="warn"><span>Bad e-mail.</span></li></ul>';

		$this->assertSame(array('Username too short.', 'Bad e-mail.'), user_flows_errors($body));
		$this->assertSame('form errors: Username too short. | Bad e-mail.', user_flows_summary($body));
	}

	// -------------------------------------------------- what the forum answers --

	/** o_redirect_delay is '0' on a fresh install, so a redirect is a Location plus this page. */
	public function testItFindsTheDestinationOfTheRedirectPage(): void {
		$body = '<meta http-equiv="refresh" content="0;URL=http://localhost/viewtopic.php?pid=4#p4" />';

		$this->assertSame('http://localhost/viewtopic.php?pid=4#p4', user_flows_redirect_target($body));
		$this->assertSame('', user_flows_redirect_target('<html><body>no redirect here</body></html>'));
	}

	public function testItResolvesTheUrlsTheForumRenders(): void {
		$page = 'http://localhost/admin/forums.php?del_forum=2';

		$this->assertSame('http://localhost/admin/forums.php', user_flows_resolve($page, 'http://localhost/admin/forums.php'));
		$this->assertSame('http://localhost/index.php', user_flows_resolve($page, '/index.php'));
		$this->assertSame('http://localhost/admin/bans.php?add_ban=2', user_flows_resolve($page, 'bans.php?add_ban=2'));
		$this->assertSame($page, user_flows_resolve($page, ''));
	}

	/** viewtopic.php renders the moderation actions as links carrying a token. */
	public function testItReadsTheModerationLinksOfATopic(): void {
		$body = '<span><a class="mod-option" href="http://localhost/moderate.php?fid=1&amp;close=2&amp;csrf_token=abc">Close topic</a></span>'.
			'<span><a class="mod-option" href="http://localhost/moderate.php?fid=1&amp;stick=2&amp;csrf_token=def">Stick topic</a></span>';

		$links = user_flows_mod_links($body);

		$this->assertSame(array('Close topic', 'Stick topic'), array_keys($links));
		$this->assertSame('http://localhost/moderate.php?fid=1&close=2&csrf_token=abc', user_flows_mod_link($links, 'Close'));
		$this->assertSame('http://localhost/moderate.php?fid=1&stick=2&csrf_token=def', user_flows_mod_link($links, 'Stick'));
		$this->assertSame('', user_flows_mod_link($links, 'Unstick'));
	}

	/** Every step of the pass is a function that exists. */
	public function testEveryStepIsCallable(): void {
		$steps = user_flows_steps();

		$this->assertNotSame(array(), $steps);

		foreach ($steps as $step)
		{
			$this->assertIsString($step[0]);
			$this->assertTrue(function_exists($step[1]), $step[1]);
		}
	}
}
