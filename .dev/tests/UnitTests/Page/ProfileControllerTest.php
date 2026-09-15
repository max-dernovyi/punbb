<?php
/**
 * profile.php as a module, with no forum: who reads a profile, the profile as
 * a whole for a visitor who may not change it, and each section with its
 * menu and the numbers observers count on from.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Profile\Event\ModeratorForumRendering;
use PunBB\Module\Profile\Event\ProfileActionRequested;
use PunBB\Module\Profile\Event\ProfileDetailsSelected;
use PunBB\Module\Profile\Event\ProfileMenuAssembling;
use PunBB\Module\Profile\Event\ProfileRendering;
use PunBB\Module\Profile\Event\ProfileRequested;
use PunBB\Module\Profile\Event\ProfileSectionRequested;
use PunBB\Module\Profile\Model\ModeratableForum;
use PunBB\Module\Profile\Model\Moderator;
use PunBB\Module\Site\Visitor\GroupPermission;

require_once __DIR__.'/ProfileFakes.php';

class ProfileControllerTest extends TestCase {
	private PageKit $kit;

	private ProfileKit $profile;

	protected function setUp(): void {
		$this->kit = new PageKit(array(ProfileRequested::class, ProfileActionRequested::class, ProfileDetailsSelected::class, ProfileMenuAssembling::class,
			ProfileRendering::class, ModeratorForumRendering::class, ProfileSectionRequested::class, MessageShowing::class, MessageRendering::class));
		$this->profile = new ProfileKit($this->kit);

		$this->profile->services->users[3] = FakeProfileServices::member(array(
			'realname' => 'Mem <b>', 'location' => 'darn town', 'url' => 'http://example.com/?a=1&b=2', 'facebook' => 'mem', 'twitter' => 'https://twitter.com/mem',
			'jabber' => 'mem@jabber.org', 'icq' => '12345', 'skype' => 'mem.skype', 'signature' => 'Sig & more', 'avatar' => 1, 'avatar_width' => 20, 'avatar_height' => 30,
			'admin_note' => 'Watch "him"', 'last_post' => 500,
		));
		$this->profile->services->users[2] = FakeProfileServices::member(array('id' => 2, 'username' => 'admin', 'g_id' => 1, 'group_id' => 1, 'email_setting' => 0));
	}

	/** @param array<string, mixed> $query */
	private function page(array $query): string {
		return $this->profile->page($query);
	}

	private function head(): \PunBB\Module\Layout\Chrome\PageHead {
		return $this->kit->chromes->opened[count($this->kit->chromes->opened) - 1];
	}

	/** @return list<string> */
	private function crumbs(): array {
		return array_map(static fn ($crumb): string => $crumb->text, $this->head()->crumbs);
	}

	/** @param list<GroupPermission> $permissions */
	private function visitor(int $id, array $permissions, bool $administrator = false): void {
		$this->kit->visitor->id = $id;
		$this->kit->visitor->permissions = $permissions;
		$this->kit->visitor->administrator = $administrator;
		$this->kit->visitor->moderating = $administrator || in_array(GroupPermission::Moderate, $permissions, true);
	}

	public function testAProfileIsReadOnlyWhereTheVisitorMayReadTheBoardAndTheMembers(): void {
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('id' => '1')));
		$this->assertSame('ProfileRequested', $this->kit->events->dispatched[0]);

		$this->visitor(5, array(GroupPermission::ViewUsers));
		$this->assertStringContainsString('<p>You do not have permission to view these forums.</p>', $this->page(array('id' => '3')));

		$this->visitor(5, array(GroupPermission::ReadBoard));
		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('id' => '3')));
		$this->profile->services->users[5] = FakeProfileServices::member(array('id' => 5));
		$this->assertStringContainsString('Welcome to your profile', $this->page(array('id' => '5')), 'a member without the members list still reads their own');

		$this->visitor(5, array(GroupPermission::ReadBoard, GroupPermission::ViewUsers));
		$this->assertStringContainsString('Bad request', $this->page(array('id' => '99')));

		$this->kit->visitor->guest = true;
		$this->visitor(1, array());
		$this->assertStringNotContainsString('permission', $this->page(array('id' => '3', 'action' => 'change_pass', 'key' => 'nope')), 'a guest following a reset key needs no permission');
	}

	public function testAVisitorWhoMayNotChangeTheProfileSeesItAsAWhole(): void {
		$this->visitor(5, array(GroupPermission::ReadBoard, GroupPermission::ViewUsers, GroupPermission::Search, GroupPermission::SendEmail));
		$this->kit->settings->values['o_censoring'] = '1';
		$this->kit->settings->values['o_show_post_count'] = '0';

		$body = $this->page(array('id' => '3', 'section' => 'identity'));

		$this->assertSame(array('profile', true, array()), array($this->head()->id, $this->head()->indexable, $this->head()->menu));
		$this->assertSame(array('Board & Co', 'member\'s profile'), $this->crumbs());
		$this->assertStringStartsWith("200  [profile]<div class=\"main-head\">\n\t\t<h2 class=\"hn\"><span>Welcome to member's profile</span></h2>", $body);

		$this->assertStringContainsString("<ul class=\"user-ident ct-legend\">\n\t\t\t\t\t\t<li class=\"username nickname\"><strong>member</strong></li>\n\t\t\t\t\t\t<li class=\"useravatar\"><img src=\"avatar/3?fresh\" alt=\"member\" /></li>\n\t\t\t\t\t\t<li class=\"usertitle\"><span>[title of member]</span></li>\t\t\t\t\t</ul>", $body);
		$this->assertStringContainsString('<li><span>Real name: <strong class="fn">Mem &lt;b&gt;</strong></span></li>', $body);
		$this->assertStringContainsString('<li><span>From: <strong> d*rn town</strong></span></li>', $body);
		$this->assertStringContainsString('<li><span>Registered: <strong> <time>100</time></strong></span></li>', $body);
		$this->assertStringContainsString('<li><span>Last post: <strong> <time>500</time></strong></span></li>', $body);
		$this->assertStringNotContainsString('Posts: ', $body, 'the post count is the staff\'s where the board hides it');

		$this->assertStringContainsString("<h3 class=\"ct-legend hn\"><span>Contact information</span></h3>\n\t\t\t\t\t<ul class=\"data-list\">\n\t\t\t\t\t\t<li><span>Email: <a href=\"/email/3?a=1&amp;b=2\">Send forum email</a></span></li>", $body);
		$this->assertStringNotContainsString('mailto:', $body, 'the address is hidden where the member hides it');
		$this->assertStringContainsString('<li><span>Website: <a href="href:http://example.com/?a=1&amp;b=2" class="external url" rel="me">text:http://example.com/?a=1&amp;b=2</a></span></li>', $body);
		$this->assertStringContainsString('<li><span>Facebook: <a href="https://www.facebook.com/mem" class="external url">https://www.facebook.com/mem</a></span></li>', $body);
		$this->assertStringContainsString('<li><span>Twitter: <a href="https://twitter.com/mem" class="external url">https://twitter.com/mem</a></span></li>', $body);
		$this->assertStringContainsString('<li><span>Jabber: <strong> mem@jabber.org</strong></span></li>', $body);
		$this->assertStringContainsString('<li><span>ICQ: <strong> 12345</strong></span></li>', $body);
		$this->assertStringNotContainsString('Skype', $body, 'the page shows no Skype, as profile.php showed none');

		$this->assertStringContainsString("<ul class=\"data-box\">\n\t\t\t\t\t\t<li class=\"first-item\"><a href=\"/search_user_posts/3?a=1&amp;b=2\">View all member's posts</a></li>\n\t\t\t\t\t\t<li><a href=\"/search_user_topics/3?a=1&amp;b=2\">View all member's topics</a></li>\t\t\t\t\t</ul>", $body);
		$this->assertStringContainsString("<div class=\"ct-set data-set set4\">\n\t\t\t\t<div class=\"ct-box data-box\">\n\t\t\t\t\t<h3 class=\"ct-legend hn\"><span>Current signature</span></h3>\n\t\t\t\t\t<div class=\"sig-demo\"><em>Sig &amp; more</em>\n</div>", $body);
		$this->assertStringEndsWith("</div>\n\t\t</div>\n\t</div>", $body);
		$this->assertNotContains('ProfileMenuAssembling', $this->kit->events->dispatched);
	}

	public function testObserversAddLinesTheProfileShows(): void {
		$this->visitor(5, array(GroupPermission::ReadBoard, GroupPermission::ViewUsers));

		$this->kit->events->observe(ProfileDetailsSelected::class, function (ProfileDetailsSelected $event): void {
			$event->set('probe', '<li>probe first</li>');
		});
		$this->kit->events->observe(ProfileRendering::class, function (ProfileRendering $event): void {
			if ($event->position() === 'pre_user_contact_info')
			{
				$event->remove(ProfileRendering::USER_CONTACT, 'jabber');
				$event->append('<!-- contacts at '.$event->itemCount().' -->');
			}

			if ($event->position() === 'pre_user_activity_info')
				$event->set(ProfileRendering::USER_ACTIVITY, 'probe', '<li>probe activity</li>');
		});

		$body = $this->page(array('id' => '3'));

		$this->assertStringContainsString("<ul class=\"user-ident ct-legend\">\n\t\t\t\t\t\t<li>probe first</li>\n\t\t\t\t\t\t<li class=\"username nickname\">", $body);
		$this->assertStringContainsString('<!-- contacts at 1 --><div class="ct-set data-set set2">', str_replace("\t", '', $body));
		$this->assertStringNotContainsString('Jabber', $body);
		$this->assertStringContainsString("<h3 class=\"ct-legend hn\"><span>Posts and topics</span></h3>\n\t\t\t\t\t<ul class=\"data-box\">\n\t\t\t\t\t\t<li>probe activity</li>\t\t\t\t\t</ul>", $body, 'an activity line an observer added shows the box the visitor had none for');
	}

	public function testTheMemberSeesTheIntroductionWithTheMenuAndWhatIsPrivate(): void {
		$this->kit->settings->values['o_show_post_count'] = '0';

		$body = $this->page(array('id' => '3'));

		$head = $this->head();
		$this->assertSame('profile-about', $head->id);
		$this->assertSame(array('Board & Co', 'member\'s profile', 'Introduction'), $this->crumbs());
		$this->assertSame(array('about', 'identity', 'settings', 'signature', 'avatar'), array_keys($head->menu));
		$this->assertSame('<li class="first-item active"><a href="/profile_about/3?a=1&amp;b=2"><span>Introduction</span></a></li>', $head->menu['about']->html);
		$this->assertSame('<li><a href="/profile_avatar/3?a=1&amp;b=2"><span>Avatar</span></a></li>', $head->menu['avatar']->html);

		$this->assertStringStartsWith("200  [profile-about]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Welcome to your profile</span></h2>\n\t</div>\n\t<div class=\"main-content main-frm\">\n\t\t<p class=\"content-options options\"><span class=\"first-item\"><a href=\"/change_password/3?a=1&amp;b=2\">Change your password</a></span> <span><a href=\"/change_email/3?a=1&amp;b=2\">Change your email address</a></span></p>", $body);
		$this->assertStringContainsString('<li><span>Last visit: <strong> <time>200</time></strong></span></li>', $body);
		$this->assertStringContainsString("<h4 class=\"ct-legend hn\"><span>Contact information</span></h4>\n\t\t\t\t\t<ul class=\"data-box\">\n\t\t\t\t\t\t<li><span>Email: <a href=\"/email/3?a=1&amp;b=2\">Send forum email</a></span></li>", $body);
		$this->assertStringContainsString('<li><span>Jabber: <strong>mem@jabber.org</strong></span></li>'."\n\t\t\t\t\t\t".'<li><span>Skype: <strong>mem.skype</strong></span></li>'."\n\t\t\t\t\t\t".'<li><span>ICQ: <strong>12345</strong></span></li>', $body);
		$this->assertStringContainsString("<li class=\"first-item\"><a href=\"/search_subscriptions/3?a=1&amp;b=2\">View all your topic subscriptions</a></li>\n\t\t\t\t\t\t<li><a href=\"/search_forum_subscriptions/3?a=1&amp;b=2\">View all your forum subscriptions</a></li>\t\t\t\t\t</ul>", $body, 'the subscriptions open the list where the member may not search');
		$this->assertStringContainsString("<div class=\"sig-demo\"><em>Sig &amp; more</em></div>", $body);
		$this->assertStringContainsString("<div id=\"private-profile\" class=\"ct-set data-set set5\">\n\t\t\t\t<div class=\"ct-box data-box\">\n\t\t\t\t\t<h3 class=\"ct-legend hn\"><span>Private information</span></h3>\n\t\t\t\t\t<ul class=\"data-list\">\n\t\t\t\t\t\t<li><span>Posts: <strong>7</strong></span></li>\n\t\t\t\t\t\t<li><span>Email: <a href=\"mailto:member@example.com\" class=\"email\">member@example.com</a></span></li>\n\t\t\t\t\t</ul>", $body);
		$this->assertStringNotContainsString('IP: ', $body);
		$this->assertStringNotContainsString('Watch', $body, 'a note is the staff\'s');
	}

	public function testTheStaffSeeTheNoteTheAddressAndTheAdministration(): void {
		$this->visitor(2, array(GroupPermission::ReadBoard, GroupPermission::ViewUsers), true);

		$body = $this->page(array('id' => '3', 'section' => 'about'));

		$this->assertSame(array('about', 'identity', 'settings', 'signature', 'avatar', 'admin'), array_keys($this->head()->menu));
		$this->assertStringContainsString('<p class="content-options options"><span class="first-item"><a href="/change_password/3?a=1&amp;b=2">Change member\'s password</a></span></p>', $body);
		$this->assertStringContainsString('<li><span>Posts: <strong>7</strong></span></li>', $body);
		$this->assertStringContainsString('<li><span>Note: <strong>Watch &quot;him&quot;</strong></span></li>', $body);
		$this->assertStringContainsString('<li><span>IP: <a href="/get_host/192.0.2.7?a=1&amp;b=2">192.0.2.7</a></span></li>', $body);
		$this->assertStringContainsString('<li><a href="/search_subscriptions/3?a=1&amp;b=2">View all member\'s topic subscriptions</a></li>', $body);
	}

	public function testTheIntroductionReachesTheActivityOnlyPastTheContacts(): void {
		$this->profile->services->users[3] = FakeProfileServices::member(array('email_setting' => 2));
		$this->kit->settings->values['o_subscriptions'] = '0';
		$positions = array();
		$this->kit->events->observe(ProfileRendering::class, function (ProfileRendering $event) use (&$positions): void {
			$positions[] = $event->position();
		});

		$body = $this->page(array('id' => '3'));

		$this->assertNotContains('pre_user_activity_info', $positions, 'profile.php ran the point inside the contacts\' box');
		$this->assertNotContains('pre_user_sig_info', $positions);
		$this->assertStringNotContainsString('Contact information', $body);
		$this->assertSame(array('output_start', 'pre_user_info', 'pre_user_ident_info', 'pre_user_contact_info', 'pre_user_private_info', 'user_info_end', 'end'), $positions);
	}

	public function testObserversChangeTheMenu(): void {
		$this->kit->events->observe(ProfileMenuAssembling::class, function (ProfileMenuAssembling $event): void {
			$event->remove('signature');
			$event->set('gallery', '<li><a href="/gallery"><span>'.$event->section().'</span></a></li>');
		});

		$this->page(array('id' => '3', 'section' => 'gallery'));

		$this->assertContains('ProfileSectionRequested', $this->kit->events->dispatched);

		$this->page(array('id' => '3', 'section' => 'about'));
		$this->assertSame(array('about', 'identity', 'settings', 'avatar', 'gallery'), array_keys($this->head()->menu));
		$this->assertSame('<li><a href="/gallery"><span>about</span></a></li>', $this->head()->menu['gallery']->html);
	}

	public function testASectionTheProfileDoesNotHaveIsABadRequestOnceObserversPassed(): void {
		$sections = array();
		$this->kit->events->observe(ProfileSectionRequested::class, function (ProfileSectionRequested $event) use (&$sections): void {
			$sections[] = $event->section();
		});

		$this->assertStringContainsString('Bad request', $this->page(array('id' => '3', 'section' => 'gallery')));
		$this->kit->settings->values['o_signatures'] = '0';
		$this->assertStringContainsString('Bad request', $this->page(array('id' => '3', 'section' => 'signature')));

		$this->assertSame(array('gallery', 'signature'), $sections);
	}

	public function testTheIdentityFormForAnAdministrator(): void {
		$this->visitor(2, array(GroupPermission::ReadBoard, GroupPermission::ViewUsers, GroupPermission::SetTitle), true);

		$body = $this->page(array('id' => '3', 'section' => 'identity'));

		$this->assertSame(array('Board & Co', 'member\'s profile', 'Identity'), $this->crumbs());
		$token = 'token-for-'.md5('/profile_identity/3?a=1&amp;b=2');
		$this->assertStringContainsString("<div class=\"hidden\">\n\t\t\t\t<input type=\"hidden\" name=\"form_sent\" value=\"1\" />\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"".$token."\" />\n\t\t\t\t<input type=\"hidden\" name=\"old_username\" value=\"member\" />\n\t\t\t</div>", $body);
		$this->assertStringContainsString("<div id=\"req-msg\" class=\"req-warn ct-box error-box\">", $body);
		$this->assertStringContainsString('<input type="text" id="fld1" name="req_username" value="member" size="35" maxlength="25" required />', $body);
		$this->assertStringContainsString('<input type="email" id="fld2" name="req_email" value="member@example.com" size="35" maxlength="80" required />', $body);
		$this->assertStringContainsString("<fieldset class=\"frm-group group2\">\n\t\t\t\t<legend class=\"group-legend\"><strong>Personal details</strong></legend>\n\t\t\t\t<div class=\"sf-set set1\">", $body);
		$this->assertStringContainsString('<input type="text" id="fld3" name="form[realname]" value="Mem &lt;b&gt;" size="35" maxlength="40" />', $body);
		$this->assertStringContainsString('<input type="text" id="fld4" name="title" value="" size="35" maxlength="50" />', $body);
		$this->assertStringContainsString('<input id="fld6" type="text" name="admin_note" value="Watch &quot;him&quot;" size="35" maxlength="30" />', $body);
		$this->assertStringContainsString('<input type="number" id="fld7" name="num_posts" value="7" size="8" maxlength="8" />', $body);
		$this->assertStringContainsString("<fieldset class=\"frm-group group3\">\n\t\t\t\t<legend class=\"group-legend\"><strong>Contact details</strong></legend>\n\t\t\t\t<div class=\"sf-set set1\">", $body);
		$this->assertStringContainsString("<fieldset class=\"frm-group group4\">", $body);
		$this->assertStringContainsString("<div class=\"sf-set set5\">\n\t\t\t\t\t<div class=\"sf-box text\">\n\t\t\t\t\t\t<label for=\"fld12\"><span>Jabber</span>", $body, 'the messengers number their items on from the contacts');
		$this->assertStringContainsString('<input id="fld15" type="text" name="form[icq]" value="12345" size="20" maxlength="12" />', $body);
	}

	public function testTheMembersIdentityFormHasNoRequiredFields(): void {
		$positions = array();
		$this->kit->events->observe(ProfileRendering::class, function (ProfileRendering $event) use (&$positions): void {
			$positions[] = $event->position();
		});

		$body = $this->page(array('id' => '3', 'section' => 'identity'));

		$this->assertStringNotContainsString('req-msg', $body);
		$this->assertStringNotContainsString('old_username', $body);
		$this->assertStringContainsString("<fieldset class=\"frm-group group1\">\n\t\t\t\t<legend class=\"group-legend\"><strong>Personal details</strong></legend>", $body);
		$this->assertStringContainsString('<input type="text" id="fld1" name="form[realname]"', $body);
		$this->assertStringNotContainsString('name="title"', $body);
		$this->assertStringContainsString('<input type="text" id="fld2" name="form[location]"', $body);
		$this->assertNotContains('pre_req_info_fieldset', $positions);
		$this->assertContains('pre_title', $positions);
	}

	public function testTheSettingsForm(): void {
		$this->profile->services->users[3] = FakeProfileServices::member(array('timezone' => '5.50', 'time_format' => 1, 'disp_topics' => 20, 'email_setting' => 2, 'auto_notify' => 1, 'show_sig' => 0));
		$this->profile->services->languages = array('Russian', 'English', 'deutsch');
		$this->kit->formatter->timeFormats = array(0 => 'H:i:s', 1 => 'H:i', 2 => 'H:i:s');

		$body = $this->page(array('id' => '3', 'section' => 'settings'));

		$this->assertSame('profile-settings', $this->head()->id);
		$this->assertStringContainsString("<select id=\"fld1\" name=\"form[language]\">\n\t\t\t\t\t\t<option value=\"deutsch\">deutsch</option>\n\t\t\t\t\t\t<option value=\"English\" selected=\"selected\">English</option>\n\t\t\t\t\t\t<option value=\"Russian\">Russian</option>\n\t\t\t\t\t\t</select>", $body, 'the languages are sorted, as profile.php sorted them');
		$this->assertStringContainsString('<option value="5.5" selected="selected">(UTC+05:30) India, Sri Lanka</option>', $body);
		$this->assertStringContainsString("<select id=\"fld4\" name=\"form[time_format]\">\n\t\t\t\t\t\t<option value=\"0\"><now Time H:i:s> (default)</option>\n\t\t\t\t\t\t<option value=\"1\" selected=\"selected\"><now Time H:i></option>\n\t\t\t\t\t\t</select>", $body, 'a format listed twice is offered once');
		$this->assertStringContainsString("\t\t\t\t<input type=\"hidden\" name=\"form[style]\" value=\"Oxygen\" />\n\t\t\t\t<fieldset class=\"mf-set set1\">", $body, 'a single style is posted, not offered');
		$this->assertStringContainsString('<input type="checkbox" id="fld10" name="form[show_sig]" value="1" /></span>', $body);
		$this->assertStringContainsString('<input type="text" id="fld11" name="form[disp_topics]" value="20" size="6" maxlength="3" />', $body);
		$this->assertStringContainsString('<input type="text" id="fld12" name="form[disp_posts]" value="" size="6" maxlength="3" />', $body);
		$this->assertStringContainsString('<input type="radio" id="fld15" name="form[email_setting]" value="2" checked="checked" />', $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld17" name="form[auto_notify]" value="1" checked="checked" />', $body);
	}

	public function testTheSignatureFormShowsTheSignatureAndWhatItMayUse(): void {
		$this->kit->language->real[] = 'help';
		$this->kit->settings->values['p_sig_img_tag'] = '0';

		$body = $this->page(array('id' => '3', 'section' => 'signature'));

		$this->assertStringContainsString('<p class="content-options options">You may use: <span class="first-item"><a class="exthelp" href="/help/bbcode?a=1&amp;b=2" title="Help with: BBCode">BBCode</a></span> <span><a class="exthelp" href="/help/smilies?a=1&amp;b=2" title="Help with: Smilies">Smilies</a></span></p>', $body);
		$this->assertStringContainsString("<div class=\"ct-set set1\">\n\t\t\t\t\t<div class=\"ct-box\">\n\t\t\t\t\t\t<h3 class=\"ct-legend hn\">Current signature</h3>\n\t\t\t\t\t\t<div class=\"sig-demo\"><em>Sig &amp; more</em></div>", $body);
		$this->assertStringContainsString('<small>Maximum size 400 characters long and 4 lines high.</small>', $body);
		$this->assertStringContainsString('<textarea id="fld1" name="signature" rows="4" cols="65">Sig &amp; more</textarea>', $body);
	}

	public function testTheAvatarFormOffersToDeleteTheAvatar(): void {
		$body = $this->page(array('id' => '3', 'section' => 'avatar'));

		$this->assertStringContainsString("<input type=\"hidden\" name=\"form_sent\" value=\"1\" />\n\t\t\t\t<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"10240\" />\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\"", $body);
		$this->assertStringContainsString('<li><span>The maximum image size allowed is 60x60 pixels and 10&#160;240 bytes (10 KB).</span></li>', $body);
		$this->assertStringContainsString('<p class="avatar-demo"><span><img src="avatar/3?fresh" alt="member" /></span></p>', $body);
		$this->assertStringContainsString('<p><a href="/delete_avatar/3/'.$this->kit->tokens->token('delete_avatar33').'?a=1&amp;b=2">', $body);
		$this->assertStringContainsString('<input id="fld1" name="req_file" type="file" size="40" />', $body);

		$this->profile->services->users[3] = FakeProfileServices::member(array());
		$body = $this->page(array('id' => '3', 'section' => 'avatar'));
		$this->assertStringNotContainsString('avatar-demo', $body);
		$this->assertStringContainsString('<p>No avatar is currently uploaded.</p>', $body);
	}

	public function testTheAdministrationForAnAdministratorOfAModerator(): void {
		$this->visitor(2, array(GroupPermission::ReadBoard, GroupPermission::ViewUsers), true);
		$this->profile->services->users[3] = FakeProfileServices::member(array('g_id' => 4, 'group_id' => 4, 'g_moderator' => 1));
		$this->profile->services->moderatable = array(
			new ModeratableForum(1, 'Cat <1>', 1, 'News', array(new Moderator(3, 'member'))),
			new ModeratableForum(1, 'Cat <1>', 2, 'Chat & more', array()),
			new ModeratableForum(2, 'Two', 3, 'Help', array()),
		);
		$this->kit->events->observe(ModeratorForumRendering::class, function (ModeratorForumRendering $event): void {
			if ($event->position() === ModeratorForumRendering::END && $event->forum()->forumId() === 2)
				$event->append("<!-- after 2 at ".$event->fieldCount()." -->\n");
		});

		$body = $this->page(array('id' => '3', 'section' => 'admin'));

		$this->assertSame(array('profile-admin', array('Board & Co', 'member\'s profile', 'Administration')), array($this->head()->id, $this->crumbs()));
		$this->assertStringContainsString("<div class=\"frm-group group1\">\n\t\t\t<div class=\"ct-set set1\">\n\t\t\t\t<div class=\"ct-box\">\n\t\t\t\t\t<h3 class=\"ct-legend hn\">Ban user</h3>\n\t\t\t\t<p><a href=\"/admin_bans?a=1&amp;b=2&amp;add_ban=3\">", $body);
		$this->assertStringContainsString("<div class=\"ct-set set2\">\n\t\t\t\t<div class=\"ct-box\">\n\t\t\t\t\t<h3 class=\"ct-legend hn\">Delete user</h3>\n\t\t\t\t<p><a href=\"/delete_user/3?a=1&amp;b=2\">", $body);
		$this->assertStringContainsString("<div class=\"sf-set set3\">\n\t\t\t\t<div class=\"sf-box select\">\n\t\t\t\t\t<label for=\"fld1\"><span>Assign user to group</span></label><br />\n\t\t\t\t\t<span class=\"fld-input\"><select id=\"fld1\" name=\"group_id\">\n\t\t\t\t\t\t<option value=\"1\">Administrators</option>\n\t\t\t\t\t\t<option value=\"3\">Members &lt;m&gt;</option>\n\t\t\t\t\t\t<option value=\"4\" selected=\"selected\">Moderators</option>\n\t\t\t\t\t</select></span>", $body);
		$this->assertStringContainsString("<div class=\"checklist\">\n\t\t\t\t\t\t<fieldset>\n\t\t\t\t\t\t\t<legend><span>Cat &lt;1&gt;:</span></legend>\n\t\t\t\t\t\t\t<div class=\"checklist-item\"><span class=\"fld-input\"><input type=\"checkbox\" id=\"fld2\" name=\"moderator_in[1]\" value=\"1\" checked=\"checked\" /></span> <label for=\"fld2\">News</label></div>\n\t\t\t\t\t\t\t<div class=\"checklist-item\"><span class=\"fld-input\"><input type=\"checkbox\" id=\"fld3\" name=\"moderator_in[2]\" value=\"1\" /></span> <label for=\"fld3\">Chat &amp; more</label></div>\n<!-- after 2 at 3 -->\n\n\t\t\t\t\t\t</fieldset>\n\t\t\t\t\t\t<fieldset>\n\t\t\t\t\t\t\t<legend><span>Two:</span></legend>\n\t\t\t\t\t\t\t<div class=\"checklist-item\"><span class=\"fld-input\"><input type=\"checkbox\" id=\"fld4\" name=\"moderator_in[3]\" value=\"1\" /></span> <label for=\"fld4\">Help</label></div>\n\t\t\t\t\t\t</fieldset>\n\t\t\t\t\t</div>", $body, 'the category heading is escaped, as profile.php escaped it');
		$this->assertStringContainsString("<div class=\"mf-set button-set set6\">", $body);
	}

	public function testAModeratorBansButNeitherDeletesNorMovesNorOpensTheirOwn(): void {
		$this->visitor(4, array(GroupPermission::ReadBoard, GroupPermission::ViewUsers, GroupPermission::Moderate, GroupPermission::BanUsers, GroupPermission::EditUsers));

		$body = $this->page(array('id' => '3', 'section' => 'admin'));

		$this->assertStringContainsString('Ban user', $body);
		$this->assertStringNotContainsString('Delete user', $body);
		$this->assertStringNotContainsString('name="group_id"', $body);
		$this->assertStringNotContainsString('checklist', $body);

		$this->profile->services->users[4] = FakeProfileServices::member(array('id' => 4, 'username' => 'mod', 'g_id' => 4, 'g_moderator' => 1));
		$this->assertStringContainsString('Bad request', $this->page(array('id' => '4', 'section' => 'admin')));
		$this->assertNotContains('admin', array_keys($this->head()->menu), 'a moderator\'s own profile offers no administration');
	}
}
