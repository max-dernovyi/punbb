<?php
/**
 * What profile.php changes, as a module with no forum: a password with the old
 * one or a reset key, an address at once or by a mailed key, a member deleted,
 * their avatar, group and forums moderated, and each section's form checked
 * and stored, an avatar upload among them.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Message\Event\ConfirmFormRendering;
use PunBB\Module\Message\Event\ConfirmFormRequested;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Profile\Event\ActivationMailing;
use PunBB\Module\Profile\Event\AvatarDeletionStep;
use PunBB\Module\Profile\Event\AvatarUploadStep;
use PunBB\Module\Profile\Event\BanRequested;
use PunBB\Module\Profile\Event\DetailsUpdateStep;
use PunBB\Module\Profile\Event\EmailChangeStep;
use PunBB\Module\Profile\Event\GroupMembershipStep;
use PunBB\Module\Profile\Event\ModeratorAssignmentStep;
use PunBB\Module\Profile\Event\PasswordChangeStep;
use PunBB\Module\Profile\Event\ProfileActionRequested;
use PunBB\Module\Profile\Event\ProfileMenuAssembling;
use PunBB\Module\Profile\Event\ProfileRendering;
use PunBB\Module\Profile\Event\UserDeletionStep;
use PunBB\Module\Profile\Model\ForumModerators;
use PunBB\Module\Profile\Model\Moderator;
use PunBB\Module\Site\Visitor\GroupPermission;

require_once __DIR__.'/ProfileFakes.php';

class ProfileChangesTest extends TestCase {
	private PageKit $kit;

	private ProfileKit $profile;

	private FakeProfileServices $services;

	protected function setUp(): void {
		$this->kit = new PageKit(array(PasswordChangeStep::class, EmailChangeStep::class, ActivationMailing::class, UserDeletionStep::class, AvatarDeletionStep::class,
			GroupMembershipStep::class, ModeratorAssignmentStep::class, BanRequested::class, DetailsUpdateStep::class, AvatarUploadStep::class, ProfileActionRequested::class,
			ProfileMenuAssembling::class, ProfileRendering::class, MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class,
			ConfirmFormRequested::class, ConfirmFormRendering::class));
		$this->profile = new ProfileKit($this->kit);
		$this->services = $this->profile->services;
		$this->kit->language->real[] = 'profile';

		$this->services->users[3] = FakeProfileServices::member(array());
		$this->services->users[2] = FakeProfileServices::member(array('id' => 2, 'username' => 'admin', 'g_id' => 1, 'group_id' => 1));
		$this->services->users[4] = FakeProfileServices::member(array('id' => 4, 'username' => 'mod', 'g_id' => 4, 'group_id' => 4, 'g_moderator' => 1));
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $files
	 */
	private function page(array $query, array $post = array(), array $files = array()): string {
		return $this->profile->page($query, $post, $files);
	}

	private function administrator(): void {
		$this->kit->visitor->id = 2;
		$this->kit->visitor->administrator = true;
		$this->kit->visitor->moderating = true;
	}

	/** @var list<string> the step of each event recorded */
	private array $steps = array();

	/** Records the step of each event of $class dispatched. */
	private function recordSteps(string $class): void {
		$this->kit->events->observe($class, function (object $event): void {
			$this->steps[] = method_exists($event, 'step') ? $event->step() : $event->stage();
		});
	}

	public function testAMemberChangesTheirPasswordWithTheOldOne(): void {
		$steps = array();
		$this->kit->events->observe(PasswordChangeStep::class, function (PasswordChangeStep $event) use (&$steps): void {
			$steps[] = $event->step().($event->withKey() ? ' with key' : '').' '.$event->hash();
		});

		$this->assertStringContainsString('<li class="warn"><span>Passwords must be at least 4 characters long.', $this->page(array('id' => '3', 'action' => 'change_pass'), array('form_sent' => '1', 'req_old_password' => 'old', 'req_new_password1' => 'abc', 'req_new_password2' => 'abc')));
		$this->assertStringContainsString('<li class="warn"><span>Passwords do not match.</span></li>', $this->page(array('id' => '3', 'action' => 'change_pass'), array('form_sent' => '1', 'req_old_password' => 'old', 'req_new_password1' => 'abcd', 'req_new_password2' => 'abce')));
		$refused = $this->page(array('id' => '3', 'action' => 'change_pass'), array('form_sent' => '1', 'req_old_password' => 'wrong', 'req_new_password1' => 'abcd', 'req_new_password2' => 'abcd'));
		$this->assertStringContainsString("<ul class=\"error-list\">\n\t\t\t\t<li class=\"warn\"><span>The old password you entered was incorrect.</span></li>\n\t\t\t</ul>", $refused);
		$this->assertStringContainsString('name="req_old_password" size="35" value="wrong" required />', $refused);
		$this->assertSame(array(), $this->services->log);

		$this->services->cookieExpiry = time() + 7200;
		$response = $this->page(array('id' => '3', 'action' => 'change_pass'), array('form_sent' => '1', 'req_old_password' => ' old ', 'req_new_password1' => 'abcd', 'req_new_password2' => 'abcd'));

		$this->assertStringStartsWith('302 /profile_about/3?a=1&b=2 [redirect]', $response);
		$this->assertSame(array('change password of 3 to hash:abcd', 'sign in 3 with hash:abcd and salty for 1209600'), $this->services->log, 'a remembered login stays remembered');
		$this->assertSame(array('Password updated.'), $this->kit->flash->info);
		$this->assertSame(array('selected ', 'submitted ', 'selected ', 'submitted ', 'selected ', 'submitted ', 'selected ', 'submitted ', 'changed hash:abcd'), $steps);
	}

	public function testObserversAddErrorsThatStopAPasswordChange(): void {
		$this->kit->events->observe(PasswordChangeStep::class, function (PasswordChangeStep $event): void {
			if ($event->step() === PasswordChangeStep::SUBMITTED)
				$event->setErrors(array('Probe <b>error</b>'));
		});

		$refused = $this->page(array('id' => '3', 'action' => 'change_pass'), array('form_sent' => '1', 'req_old_password' => 'old', 'req_new_password1' => 'abcd', 'req_new_password2' => 'abcd'));

		$this->assertStringContainsString("<ul class=\"error-list\">\n\t\t\t\t<li class=\"warn\"><span>Probe <b>error</b></span></li>\n\t\t\t</ul>", $refused);
		$this->assertSame(array(), $this->services->log);
	}

	public function testTheStaffSetAPasswordWithoutTheOldOneButNotOnAnotherModerator(): void {
		$this->administrator();

		$form = $this->page(array('id' => '3', 'action' => 'change_pass'));
		$this->assertSame(array('Board & Co', 'member\'s profile', 'Change member\'s password'), array_map(static fn ($crumb): string => $crumb->text, $this->kit->chromes->opened[0]->crumbs));
		$this->assertStringNotContainsString('req_old_password', $form);
		$this->assertStringContainsString("<div class=\"sf-set set1 prepend-top\">\n\t\t\t\t\t<div class=\"sf-box text required\">\n\t\t\t\t\t\t<label for=\"fld1\"><span>New password</span>", $form);

		$this->page(array('id' => '3', 'action' => 'change_pass'), array('form_sent' => '1', 'req_new_password1' => 'abcd', 'req_new_password2' => 'abcd'));
		$this->assertSame(array('change password of 3 to hash:abcd'), $this->services->log, 'the staff sign nobody in');

		$this->kit->visitor->id = 5;
		$this->kit->visitor->administrator = false;
		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard, GroupPermission::ViewUsers, GroupPermission::Moderate, GroupPermission::EditUsers, GroupPermission::ChangePasswords);
		$this->assertStringContainsString('You do not have permission', $this->page(array('id' => '4', 'action' => 'change_pass')));
		$this->assertStringNotContainsString('permission', $this->page(array('id' => '3', 'action' => 'change_pass')));

		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard, GroupPermission::ViewUsers, GroupPermission::Moderate, GroupPermission::EditUsers);
		$this->assertStringContainsString('You do not have permission', $this->page(array('id' => '3', 'action' => 'change_pass')));
	}

	public function testAGuestResetsThePasswordWithTheKeyMailedThere(): void {
		$this->services->users[3] = FakeProfileServices::member(array('activate_key' => 'KEY12345', 'last_email_sent' => time() - 60));

		$this->assertStringContainsString('A user is currently logged in.', $this->page(array('id' => '3', 'action' => 'change_pass', 'key' => 'KEY12345')));

		$this->kit->visitor->guest = true;
		$this->kit->visitor->id = 1;
		$this->kit->visitor->permissions = array();

		$bad = 'The specified password activation key was incorrect or has expired. Please re-request a new password. If that fails, contact the forum administrator at <a href="mailto:admin@example.com">admin@example.com</a>.';
		$this->assertStringContainsString($bad, $this->page(array('id' => '3', 'action' => 'change_pass', 'key' => 'KEY1234')));
		$this->assertStringContainsString($bad, $this->page(array('id' => '3', 'action' => 'change_pass', 'key' => array('KEY12345'))));

		$form = $this->page(array('id' => '3', 'action' => 'change_pass', 'key' => 'KEY12345'));
		$head = $this->kit->chromes->opened[count($this->kit->chromes->opened) - 1];
		$this->assertSame(array('profile-changepass', 'key'), array($head->id, $head->view));
		$this->assertStringContainsString('action="/change_password_key/3/KEY12345?a=1&amp;b=2" autocomplete="off">', $form);
		$this->assertStringContainsString('<input type="hidden" name="csrf_token" value="'.$this->kit->tokens->token('/change_password_key/3/KEY12345?a=1&amp;b=2').'" />', $form);

		$response = $this->page(array('id' => '3', 'action' => 'change_pass', 'key' => 'KEY12345'), array('form_sent' => '1', 'req_new_password1' => 'abcd', 'req_new_password2' => 'abcd'));
		$this->assertStringStartsWith('302 /index?a=1&b=2 ', $response);
		$this->assertSame(array('reset password of 3 to hash:abcd'), $this->services->log);

		$this->services->users[3] = FakeProfileServices::member(array('activate_key' => 'KEY12345', 'last_email_sent' => time() - 3600));
		$this->assertStringContainsString($bad, $this->page(array('id' => '3', 'action' => 'change_pass', 'key' => 'KEY12345')), 'a key expires once its lifetime has passed');
	}

	public function testAnAddressIsChangedAtOnceWhereTheBoardDoesNotVerify(): void {
		$this->recordSteps(EmailChangeStep::class);
		$this->kit->settings->values['o_mailing_list'] = 'list@example.com';
		$this->services->banned = array('banned@example.com');
		$this->services->addresses = array('banned@example.com' => array('other'));

		$refused = $this->page(array('id' => '3', 'action' => 'change_email'), array('form_sent' => '1', 'req_password' => 'nope', 'req_new_email' => 'bad'));
		$this->assertStringContainsString("<li class=\"warn\"><span>The password you entered was incorrect.</span></li>\n\t\t\t\t<li class=\"warn\"><span>The email address you entered is invalid.</span></li>", $refused);
		$this->assertStringContainsString('name="req_new_email" size="35" maxlength="80" value="bad" required />', $refused);

		$response = $this->page(array('id' => '3', 'action' => 'change_email'), array('form_sent' => '1', 'req_password' => 'secret', 'req_new_email' => ' Banned@Example.com '));

		$this->assertStringStartsWith('302 /profile_about/3?a=1&b=2 ', $response);
		$this->assertSame(array(
			'mail list@example.com: Alert - Banned e-mail detected | User \'member\' changed to banned e-mail address: banned@example.com'."\n\n".'User profile: /user/3?a=1&amp;b=2'."\n\n".'-- '."\n".'Forum Mailer'."\n".'(Do not reply to this message)',
			'mail list@example.com: Alert - Duplicate e-mail detected | User \'member\' changed to an e-mail address that also belongs to: other'."\n\n".'User profile: /user/3?a=1&amp;b=2'."\n\n".'-- '."\n".'Forum Mailer'."\n".'(Do not reply to this message)',
			'change email of 3 to banned@example.com',
		), $this->services->log);
		$this->assertSame(array('selected', 'submitted', 'selected', 'submitted', 'banned', 'duplicate'), $this->steps);

		$this->services->log = array();
		$this->kit->settings->values['p_allow_banned_email'] = '0';
		$this->kit->settings->values['p_allow_dupe_email'] = '0';
		$this->assertStringContainsString("<li class=\"warn\"><span>The email address you entered is banned in this forum. Please choose another email address.</span></li>\n\t\t\t\t<li class=\"warn\"><span>Someone else is already registered with that email address.", $this->page(array('id' => '3', 'action' => 'change_email'), array('form_sent' => '1', 'req_password' => 'secret', 'req_new_email' => 'banned@example.com')));
		$this->assertSame(array(), $this->services->log);
	}

	public function testAnAddressIsMailedItsKeyWhereTheBoardVerifies(): void {
		$this->kit->settings->values['o_regs_verify'] = '1';
		$this->kit->language->mailTemplates['activate_email'] = "Subject: Change address\n\nHello <username>, follow <activation_url> on <base_url>.\n<board_mailer>";
		$this->kit->events->observe(ActivationMailing::class, function (ActivationMailing $event): void {
			$event->compose($event->subject().' ('.$event->key().')', $event->message().' (probed)');
		});

		$response = $this->page(array('id' => '3', 'action' => 'change_email'), array('form_sent' => '1', 'req_password' => 'secret', 'req_new_email' => 'new@example.com'));

		$this->assertStringContainsString('An email has been sent to the specified address with instructions on how to activate the new email address.', $response);
		$this->assertSame(array(
			'request email of 3 to new@example.com with KKKKKKKK',
			'mail new@example.com: Change address (KKKKKKKK) | Hello member, follow /change_email_key/3/KKKKKKKK?a=1&b=2 on http://forum.test/.'."\n".'Board & Co Mailer (probed)',
		), $this->services->log);
	}

	public function testAKeyConfirmsTheAddressAskedFor(): void {
		$this->services->users[3] = FakeProfileServices::member(array('activate_key' => 'KEY12345'));

		$this->assertStringContainsString('The specified email activation key was incorrect', $this->page(array('id' => '3', 'action' => 'change_email', 'key' => 'nope')));
		$this->assertStringContainsString('Your email address has been updated.', $this->page(array('id' => '3', 'action' => 'change_email', 'key' => 'KEY12345')));
		$this->assertSame(array('confirm email of 3'), $this->services->log);

		$this->kit->visitor->id = 5;
		$this->assertStringContainsString('You do not have permission', $this->page(array('id' => '3', 'action' => 'change_email', 'key' => 'KEY12345')), 'another member\'s address is the staff\'s');
	}

	public function testAnAdministratorDeletesAMemberOnceConfirmed(): void {
		$this->recordSteps(UserDeletionStep::class);

		$this->assertStringContainsString('You do not have permission', $this->page(array('id' => '3', 'action' => 'delete_user')));

		$this->administrator();
		$this->assertStringContainsString('Administrators cannot be deleted.', $this->page(array('id' => '2', 'action' => 'delete_user')));

		$form = $this->page(array('id' => '3', 'action' => 'delete_user'));
		$head = $this->kit->chromes->opened[count($this->kit->chromes->opened) - 1];
		$this->assertSame(array('dialogue', 'delete_user'), array($head->id, $head->view));
		$this->assertSame(array('Board & Co', 'member\'s profile', 'Delete user'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));
		$this->assertStringContainsString('<input type="checkbox" id="fld1" name="delete_posts" value="1" checked="checked" /></span>', $form);

		$this->assertStringStartsWith('302 /profile_admin/3?a=1&b=2 ', $this->page(array('id' => '3'), array('cancel' => '1')));

		$response = $this->page(array('id' => '3', 'action' => 'delete_user'), array('delete_user_comply' => '1', 'delete_posts' => '1'));
		$this->assertStringStartsWith('302 /index?a=1&b=2 ', $response);
		$this->assertSame(array('remove user 3 with posts'), $this->services->log);
		$this->assertSame(array('selected', 'selected', 'selected', 'selected', 'submitted', 'deleted'), $this->steps, 'a cancelled deletion is not selected');
	}

	public function testAnAvatarIsDeletedByItsLinkOrOnceConfirmed(): void {
		$this->assertStringContainsString('name="confirm_cancel"', $this->page(array('id' => '3', 'action' => 'delete_avatar', 'csrf_token' => 'forged')));
		$this->assertSame(array(), $this->services->log);

		$response = $this->page(array('id' => '3', 'action' => 'delete_avatar', 'csrf_token' => $this->kit->tokens->token('delete_avatar33')));
		$this->assertStringStartsWith('302 /profile_avatar/3?a=1&b=2 ', $response);
		$this->assertSame(array('remove avatar of 3'), $this->services->log);

		$this->kit->visitor->id = 5;
		$this->assertStringContainsString('You do not have permission', $this->page(array('id' => '3', 'action' => 'delete_avatar', 'csrf_token' => $this->kit->tokens->token('delete_avatar35'))));
	}

	public function testAnAdministratorMovesAModeratorOutOfModeration(): void {
		$this->assertStringContainsString('You do not have permission', $this->page(array('id' => '4'), array('update_group_membership' => '1', 'group_id' => '3')));

		$this->administrator();

		$this->page(array('id' => '4'), array('update_group_membership' => '1', 'group_id' => '3'));
		$this->assertSame(array('move 4 to group 3', 'check group 3', 'clean moderators'), $this->services->log);

		$this->services->log = array();
		$this->page(array('id' => '3'), array('update_group_membership' => '1', 'group_id' => '4'));
		$this->assertSame(array('move 3 to group 4', 'check group 4'), $this->services->log, 'a member who did not moderate is on no list');
	}

	public function testAnAdministratorChoosesTheForumsAModeratorModerates(): void {
		$this->administrator();
		$this->services->forums = array(
			new ForumModerators(1, array(new Moderator(4, 'mod'), new Moderator(9, 'zed'))),
			new ForumModerators(2, array(new Moderator(9, 'zed'))),
			new ForumModerators(3, array()),
		);
		$updated = array();
		$this->kit->events->observe(ModeratorAssignmentStep::class, function (ModeratorAssignmentStep $event) use (&$updated): void {
			$updated[] = $event->step().' '.implode(',', $event->forumIds());
		});

		$response = $this->page(array('id' => '4'), array('update_forums' => '1', 'moderator_in' => array('2' => '1', '3' => '1')));

		$this->assertStringStartsWith('302 /profile_admin/4?a=1&b=2 ', $response);
		$this->assertSame(array('moderators of 1: zed=9', 'moderators of 2: mod=4,zed=9', 'moderators of 3: mod=4'), $this->services->log);
		$this->assertSame(array('submitted ', 'updated 2,3'), $updated);
	}

	public function testTheStaffAllowedToBanAreSentToTheBansForm(): void {
		$this->assertStringContainsString('You do not have permission', $this->page(array('id' => '3'), array('ban' => '1')));

		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard, GroupPermission::ViewUsers, GroupPermission::Moderate, GroupPermission::BanUsers);
		$this->assertStringStartsWith('302 /admin_bans?a=1&b=2&add_ban=3 ', $this->page(array('id' => '3'), array('ban' => '1')));
	}

	public function testTheIdentityIsCheckedAndStoredWithTheNewNameEverywhere(): void {
		$this->administrator();
		$this->services->forums = array(new ForumModerators(1, array(new Moderator(4, 'mod'))), new ForumModerators(2, array()));

		$refused = $this->page(array('id' => '4', 'section' => 'identity'), array('form_sent' => '1', 'req_username' => 'm', 'old_username' => 'mod', 'req_email' => 'nope',
			'form' => array('realname' => 'Real <b>', 'icq' => '12x', 'facebook' => 'http://example.com/me', 'probe' => 'ignored')));
		$this->assertStringContainsString("<li class=\"warn\"><span>Username <b>too short</b></span></li>\n\t\t\t\t<li class=\"warn\"><span>The email address you entered is invalid.</span></li>\n\t\t\t\t<li class=\"warn\"><span>You entered an invalid Facebook account.</span></li>\n\t\t\t\t<li class=\"warn\"><span>You entered an invalid ICQ UIN.</span></li>", $refused);
		$this->assertStringContainsString('name="req_username" value="m" size="35"', $refused);
		$this->assertStringContainsString('name="form[realname]" value="Real &lt;b&gt;" size="35"', $refused, 'a refused form keeps what was typed');
		$this->assertSame(array(), $this->services->log);

		$response = $this->page(array('id' => '4', 'section' => 'identity'), array('form_sent' => '1', 'req_username' => ' moderator ', 'old_username' => 'mod', 'req_email' => 'Mod@Example.com',
			'num_posts' => '12', 'admin_note' => ' note ', 'title' => '', 'form' => array('url' => 'example.com', 'linkedin' => 'https://linkedin.com/in/mod', 'yahoo' => array('x'))));

		$this->assertStringStartsWith('302 /profile_identity/4?a=1&b=2 ', $response);
		$this->assertSame(array(
			"update 4: url='http://example.com', linkedin='https://linkedin.com/in/mod', yahoo=NULL, realname=NULL, location=NULL, jabber=NULL, icq=NULL, msn=NULL, aim=NULL, facebook=NULL, twitter=NULL, skype=NULL, username='moderator', num_posts='12', email='mod@example.com', admin_note='note', title=NULL",
			'rename posts of 4 from mod to moderator', 'rename topics of 4 from mod to moderator', 'rename topic last posters of 4 from mod to moderator',
			'rename forum last posters of 4 from mod to moderator', 'rename online of 4 from mod to moderator', 'rename editors of 4 from mod to moderator',
			'moderators of 1: moderator=4', 'rebuild bans',
		), $this->services->log);
		$this->assertSame(array('Profile updated.'), $this->kit->flash->info);
	}

	public function testAMembersTitleMayNotPassForOneTheBoardGives(): void {
		$this->kit->visitor->permissions[] = GroupPermission::SetTitle;

		$this->assertStringContainsString('The title you entered contains a forbidden word.', $this->page(array('id' => '3', 'section' => 'identity'), array('form_sent' => '1', 'title' => 'Moderator')));

		$this->page(array('id' => '3', 'section' => 'identity'), array('form_sent' => '1', 'title' => 'Chief', 'req_username' => 'hacker', 'num_posts' => '999'));
		$this->assertStringContainsString("title='Chief'", $this->services->log[0] ?? '');
		$this->assertStringNotContainsString('username', $this->services->log[0] ?? '', 'a member does not rename themselves');
		$this->assertStringNotContainsString('num_posts', $this->services->log[0] ?? '');
	}

	public function testObserversChangeWhatASectionStoresAndWhatStopsIt(): void {
		$this->kit->events->observe(DetailsUpdateStep::class, function (DetailsUpdateStep $event): void {
			if ($event->step() === DetailsUpdateStep::VALIDATING && $event->section() === 'probe')
				$event->details()->set('probe_column', 'probed');

			if ($event->step() === DetailsUpdateStep::VALIDATED && $event->details()->value('probe_column') === 'stop')
				$event->setErrors(array('Probe stop'));
		});

		$this->assertStringStartsWith('302 /profile_probe/3?a=1&b=2 ', $this->page(array('id' => '3', 'section' => 'probe'), array('form_sent' => '1')));
		$this->assertSame(array("update 3: probe_column='probed'"), $this->services->log);

		$this->services->log = array();
		$this->assertStringContainsString('Bad request', $this->page(array('id' => '3', 'section' => 'other'), array('form_sent' => '1')), 'a section that saves nothing is a bad request');
		$this->assertSame(array(), $this->services->log);
	}

	public function testTheSettingsAreClampedAndCheckedAgainstThePacks(): void {
		$this->services->languages = array('English', 'Deutsch');
		$form = array('timezone' => '5.50', 'dst' => 'on', 'time_format' => '2x', 'email_setting' => '7', 'language' => 'Deut/sch', 'disp_topics' => '1', 'disp_posts' => '', 'show_img' => '1', 'style' => 'Oxygen', 'probe' => '1');

		$response = $this->page(array('id' => '3', 'section' => 'settings'), array('form_sent' => '1', 'form' => $form));

		$this->assertStringStartsWith('302 /profile_settings/3?a=1&b=2 ', $response);
		$this->assertSame(array("update 3: timezone='5.5', dst='1', time_format='2', email_setting='1', language='Deutsch', disp_topics='3', disp_posts=NULL, show_img='1', style='Oxygen', date_format='0', notify_with_post='0', auto_notify='0', show_smilies='0', show_img_sig='0', show_avatars='0', show_sig='0'"), $this->services->log);

		$this->services->log = array();
		$this->assertStringContainsString('Bad request', $this->page(array('id' => '3', 'section' => 'settings'), array('form_sent' => '1', 'form' => array('language' => 'english'))));
		$this->assertStringContainsString('Bad request', $this->page(array('id' => '3', 'section' => 'settings'), array('form_sent' => '1', 'form' => array('style' => 'Nope'))));
		$this->assertStringContainsString('Bad request', $this->page(array('id' => '3', 'section' => 'settings'), array('form_sent' => '1', 'form' => array('timezone' => '15'))));
		$this->assertSame(array(), $this->services->log);
	}

	public function testTheSignatureIsCheckedTonedDownAndTidied(): void {
		$this->kit->settings->values['p_sig_length'] = '10';
		$this->kit->settings->values['p_sig_lines'] = '1';

		$refused = $this->page(array('id' => '3', 'section' => 'signature'), array('form_sent' => '1', 'signature' => "Too long a line\r\nand two"));
		$this->assertStringContainsString("<ul class=\"error-list\">\n\t\t\t\t<li class=\"warn\"><span>Signatures cannot be longer than 10 characters. Please reduce your signature by 13 characters.</span></li>\n\t\t\t\t\t<li class=\"warn\"><span>Signatures cannot have more than 1 lines.", $refused);
		$this->assertStringContainsString("name=\"signature\" rows=\"4\" cols=\"65\">Too long a line\r\nand two</textarea>", $refused);

		$this->kit->settings->values['p_sig_length'] = '400';
		$this->kit->settings->values['p_sig_lines'] = '4';

		$this->assertStringContainsString('Bad <b>tag</b>', $this->page(array('id' => '3', 'section' => 'signature'), array('form_sent' => '1', 'signature' => 'a [bad] tag')));

		$this->page(array('id' => '3', 'section' => 'signature'), array('form_sent' => '1', 'signature' => ' LOUD [B]VOICE[/B] '));
		$this->assertSame(array("update 3: signature='Loud [b]voice[/b]'"), $this->services->log);

		$this->kit->settings->values['o_signatures'] = '0';
		$this->assertStringContainsString('The administrator has disabled signatures support.', $this->page(array('id' => '3', 'section' => 'signature'), array('form_sent' => '1', 'signature' => 'x')));
	}

	public function testAnAvatarIsUploadedAndShown(): void {
		$stages = array();
		$this->kit->events->observe(AvatarUploadStep::class, function (AvatarUploadStep $event) use (&$stages): void {
			$stages[] = $event->stage().' '.$event->file().' '.$event->extension().' '.$event->avatarType();
		});
		$this->services->images['img/avatars/3.tmp'] = array(40, 50, IMAGETYPE_PNG);
		$upload = array('req_file' => array('tmp_name' => '/tmp/php-upload', 'type' => 'image/png', 'size' => 2048, 'error' => UPLOAD_ERR_OK, 'name' => '../../evil.php'));

		$body = $this->page(array('id' => '3', 'section' => 'avatar'), array('form_sent' => '1'), $upload);

		$this->assertStringStartsWith('200  [profile-avatar]', $body, 'the avatar\'s section is shown again with the avatar');
		$this->assertStringContainsString('<p class="avatar-demo"><span><img src="avatar/3?fresh" alt="member" /></span></p>', $body);
		$this->assertSame(array('move upload to img/avatars/3.tmp', 'remove avatar of 3', 'place img/avatars/3.tmp at img/avatars/3.png', 'avatar of 3: 3 40x50'), $this->services->log);
		$this->assertSame(array('uploaded   0', 'moved img/avatars/3.tmp  0', 'typed img/avatars/3.tmp .png 3', 'checked img/avatars/3.tmp .png 3'), $stages);
	}

	public function testAnUploadThatIsNoAllowedImageIsRefusedAndDeleted(): void {
		$upload = static fn (string $type, int $size = 2048, int $error = UPLOAD_ERR_OK): array => array('req_file' => array('tmp_name' => '/tmp/php-upload', 'type' => $type, 'size' => $size, 'error' => $error));

		$this->assertStringContainsString('<li class="warn"><span>You did not select a file for upload.</span></li>', $this->page(array('id' => '3', 'section' => 'avatar'), array('form_sent' => '1')));
		$this->assertStringContainsString('The file you tried to upload is not of an allowed type.', $this->page(array('id' => '3', 'section' => 'avatar'), array('form_sent' => '1'), $upload('text/html')));
		$this->assertStringContainsString('The file you tried to upload is larger than the maximum allowed 10&#160;240 bytes.', $this->page(array('id' => '3', 'section' => 'avatar'), array('form_sent' => '1'), $upload('image/gif', 20480)));
		$this->assertStringContainsString('The selected file was only partially uploaded. Please try again.', $this->page(array('id' => '3', 'section' => 'avatar'), array('form_sent' => '1'), $upload('image/gif', 20, UPLOAD_ERR_PARTIAL)));
		$this->assertSame(array(), $this->services->log);

		$this->services->images['img/avatars/3.tmp'] = array(40, 50, IMAGETYPE_GIF);
		$this->assertStringContainsString('The file you tried to upload is not of an allowed type.', $this->page(array('id' => '3', 'section' => 'avatar'), array('form_sent' => '1'), $upload('image/png')), 'a GIF sent as another type is dodgy');
		$this->assertSame(array('move upload to img/avatars/3.tmp', 'delete img/avatars/3.tmp'), $this->services->log);

		$this->services->log = array();
		$this->services->images['img/avatars/3.tmp'] = array(80, 50, IMAGETYPE_PNG);
		$this->assertStringContainsString('The file you tried to upload is wider and/or higher than the maximum allowed 60x60 pixels.', $this->page(array('id' => '3', 'section' => 'avatar'), array('form_sent' => '1'), $upload('image/png')));
		$this->assertSame(array('move upload to img/avatars/3.tmp', 'delete img/avatars/3.tmp'), $this->services->log);

		$this->services->log = array();
		unset($this->services->images['img/avatars/3.tmp']);
		$this->assertStringContainsString('The file you tried to upload is not of an allowed type.', $this->page(array('id' => '3', 'section' => 'avatar'), array('form_sent' => '1'), $upload('image/png')), 'a file that is no image is refused');
		$this->assertSame(array('move upload to img/avatars/3.tmp', 'delete img/avatars/3.tmp'), $this->services->log);

		$this->assertStringContainsString('Bad request', $this->page(array('id' => '3', 'section' => 'avatar'), array('form_sent' => '1'), array('req_file' => array('tmp_name' => array('a', 'b')))));
	}
}
